<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalidaOcasionalExcelExporter
{
    /**
     * @param  array{
     *   anio:int,
     *   mes:int,
     *   kpis:array{total:int,minutos:int,empleados:int},
     *   filas:list<array<string,mixed>>
     * }  $informe
     */
    public function download(array $informe): StreamedResponse
    {
        $mesLabel = LlegadaTardeService::MESES[$informe['mes']].' '.$informe['anio'];
        $generado = LlegadaTardeService::fechaHoraLabel(now('America/Bogota'));
        $filas = $this->ordenarFilas($informe['filas']);

        $libro = new Spreadsheet;
        $libro->getProperties()
            ->setCreator('Control de acceso')
            ->setTitle('Regresos tarde de salidas ocasionales')
            ->setDescription('Regresos tarde de salidas ocasionales · '.$mesLabel);

        $this->llenarResumen($libro->getActiveSheet(), $informe, $mesLabel, $generado, count($filas));
        $this->llenarDetalle($libro->createSheet(), $filas, $mesLabel);

        $libro->setActiveSheetIndex(0);

        $archivo = 'salidas-ocasionales-completo-'.$informe['anio'].'-'.str_pad((string) $informe['mes'], 2, '0', STR_PAD_LEFT).'.xlsx';

        return response()->streamDownload(function () use ($libro) {
            $writer = new Xlsx($libro);
            $writer->save('php://output');
            $libro->disconnectWorksheets();
        }, $archivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{kpis:array{total:int,minutos:int,empleados:int}}  $informe
     */
    private function llenarResumen(Worksheet $hoja, array $informe, string $mesLabel, string $generado, int $filas): void
    {
        $hoja->setTitle('Resumen');
        $kpis = $informe['kpis'];

        $hoja->setCellValue('A1', 'Regresos tarde de salidas ocasionales');
        $hoja->mergeCells('A1:B1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $hoja->fromArray([
            ['Periodo', $mesLabel],
            ['Alcance', 'Todos los empleados'],
            ['Generado', $generado],
            ['Registros', $filas],
            [null, null],
            ['Indicador', 'Valor'],
            ['Regresos tarde', $kpis['total']],
            ['Retraso acumulado', LlegadaTardeService::minutosLabel($kpis['minutos'])],
            ['Empleados', $kpis['empleados']],
        ], null, 'A2');

        $hoja->getStyle('A7:B7')->getFont()->setBold(true);
        $hoja->getStyle('A7:B7')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E3A5F');
        $hoja->getStyle('A7:B7')->getFont()->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A7:B10')->applyFromArray($this->bordes());
        $hoja->getColumnDimension('A')->setWidth(28);
        $hoja->getColumnDimension('B')->setWidth(28);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function llenarDetalle(Worksheet $hoja, array $filas, string $mesLabel): void
    {
        $hoja->setTitle('Detalle');
        $encabezados = [
            'Empleado',
            'Cédula',
            'Cargo',
            'Horario',
            'Fecha',
            'Día',
            'Salió',
            'Regreso esperado',
            'Regresó',
            'Minutos',
            'Cumplimiento',
            'Tipo',
            'Motivo',
            'Autoriza',
            'Detalle',
        ];
        $hoja->fromArray($encabezados, null, 'A1');

        $datos = [];
        foreach ($filas as $fila) {
            $fecha = $fila['fecha'] instanceof Carbon ? $fila['fecha']->format('d/m/Y') : '';
            $datos[] = [
                $fila['nombre'],
                $fila['identificacion'],
                $fila['cargo'],
                $fila['horario'] ?? '',
                $fecha,
                $fila['dia_label'],
                $fila['salio'],
                $fila['esperado'],
                $fila['regreso'],
                (int) $fila['minutos'],
                $fila['cumplimiento_label'],
                $fila['motivo_label'].(! empty($fila['permiso_intervalo']) ? ' · '.$fila['permiso_intervalo'] : ''),
                $fila['motivo'] ?? '',
                $fila['autoriza'] ?? '',
                $fila['mensaje'],
            ];
        }
        if ($datos !== []) {
            $hoja->fromArray($datos, null, 'A2');
        }

        $ultima = max(2, count($datos) + 1);
        $rango = 'A1:O'.$ultima;
        $hoja->getStyle('A1:O1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A1:O1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E3A5F');
        $hoja->getStyle('A1:O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle($rango)->applyFromArray($this->bordes());
        $hoja->setAutoFilter($rango);
        $hoja->freezePane('A2');
        $hoja->getRowDimension(1)->setRowHeight(22);

        foreach (range('A', 'O') as $col) {
            $hoja->getColumnDimension($col)->setAutoSize(true);
        }

        $hoja->getHeaderFooter()->setOddHeader('&CRegresos tarde · salidas ocasionales · '.$mesLabel.' · todos los empleados');
        $hoja->getHeaderFooter()->setOddFooter('&LControl de acceso&RPágina &P de &N');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function ordenarFilas(array $filas): array
    {
        usort($filas, function (array $a, array $b) {
            $fa = $a['fecha'] instanceof Carbon ? $a['fecha']->timestamp : 0;
            $fb = $b['fecha'] instanceof Carbon ? $b['fecha']->timestamp : 0;
            if ($fa !== $fb) {
                return $fb <=> $fa;
            }

            return strcmp((string) $a['nombre'], (string) $b['nombre']);
        });

        return array_values($filas);
    }

    /** @return array<string, mixed> */
    private function bordes(): array
    {
        return [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ];
    }
}
