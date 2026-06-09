<?php

namespace App\Actions\Regatta;

use App\Models\Regatta;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DownloadRegattaTeamsAction
{
    /**
     * Сформировать XLSX-файл со списком всех заявленных команд и их составом.
     */
    public function execute(Regatta $regatta): BinaryFileResponse
    {
        $entries = $regatta->approvedEntries()
            ->with(['team.organizer', 'team.activeMembers', 'yacht'])
            ->get();

        if ($entries->isEmpty()) {
            abort(404, 'Нет заявленных команд для скачивания');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Команды');

        $headers = ['№', 'Яхта', 'Команда', 'Капитан', 'Участник', 'Дата рождения', 'Разряд'];
        $sheet->fromArray($headers, null, 'A1');

        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F3A5F'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 2;
        $teamNumber = 0;

        foreach ($entries as $entry) {
            $teamNumber++;
            $members = $entry->team?->activeMembers ?? collect();
            $firstRow = $row;

            if ($members->isEmpty()) {
                $sheet->fromArray([
                    $teamNumber,
                    $entry->yacht?->name ?? '—',
                    $entry->team?->name ?? '—',
                    $entry->team?->organizer?->name ?? '—',
                    '—',
                    '',
                    '',
                ], null, 'A'.$row);
                $row++;
            } else {
                foreach ($members as $member) {
                    $sheet->fromArray([
                        $teamNumber,
                        $entry->yacht?->name ?? '—',
                        $entry->team?->name ?? '—',
                        $entry->team?->organizer?->name ?? '—',
                        $member->name ?? '—',
                        $member->birth_date?->format('d.m.Y') ?? '',
                        $member->sport_category?->getLabel() ?? '',
                    ], null, 'A'.$row);
                    $row++;
                }
            }

            // Объединяем ячейки с информацией о команде по всем строкам её состава
            $lastRow = $row - 1;
            if ($lastRow > $firstRow) {
                foreach (['A', 'B', 'C', 'D'] as $col) {
                    $sheet->mergeCells("{$col}{$firstRow}:{$col}{$lastRow}");
                }
            }
        }

        $lastRow = $row - 1;
        $sheet->getStyle("A1:G{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);
        $sheet->getStyle("A2:D{$lastRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $safeName = preg_replace('/[^\w\s\-а-яё]/ui', '', $regatta->name);
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)) ?: 'regatta';

        $filename = tempnam(sys_get_temp_dir(), 'regatta_teams_').'.xlsx';
        (new Xlsx($spreadsheet))->save($filename);
        $spreadsheet->disconnectWorksheets();

        return response()
            ->download($filename, "{$safeName}_teams.xlsx")
            ->deleteFileAfterSend(true);
    }
}
