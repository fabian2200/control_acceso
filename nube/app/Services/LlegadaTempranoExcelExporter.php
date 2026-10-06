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

class LlegadaTempranoExcelExporter
{
    /**
     * @param  array{
     *   anio:int,
     *   mes:int,
     *   kpis:array{total:int,minutos:int,promedio:int,empleados:int},
     *   filas:list<array<string,mixed>>,
     *   ranking:list<array<string,mixed>>
     * }  $informe
     */
    public function download(array $informe): StreamedResponse
    {
        $mesLabel = LlegadaTardeService::MESES[$informe['mes']].' '.$informe['anio'];
        $generado = LlegadaTardeService::fechaHoraLabel(now('America/Bogota'));

        $libro = new Spreadsheet;
        $libro->getProperties()
            ->setCreator('Control de acceso')
            ->setTitle('Registros de Puntualidad')
            ->setDescription('Llegadas temprano de todos los empleados · '.$mesLabel);

        $this->llenarResumen($libro->getActiveSheet(), $informe, $mesLabel, $generado);
        $this->llenarDetalle($libro->createSheet(), $informe['filas'], $mesLabel);

        $libro->setActiveSheetIndex(0);

        $archivo = 'registros-puntualidad-'.$informe['anio'].'-'.str_pad((string) $informe['mes'], 2, '0', STR_PAD_LEFT).'.xlsx';

        return response()->streamDownload(function () use ($libro) {
            $writer = new Xlsx($libro);
            $writer->save('php://output');
            $libro->disconnectWorksheets();
        }, $archivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{
     *   kpis:array{total:int,promedio:int,empleados:int},
     *   ranking:list<array<string,mixed>>,
     *   filas:list<array<string,mixed>>
     * }  $informe
     */
    private function llenarResumen(Worksheet $hoja, array $informe, string $mesLabel, string $generado): void
    {
        $hoja->setTitle('Resumen');
        $kpis = $informe['kpis'];

        $hoja->setCellValue('A1', 'Registros de Puntualidad');
        $hoja->mergeCells('A1:B1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $hoja->fromArray([
            ['Periodo', $mesLabel],
            ['Alcance', 'Todos los empleados'],
            ['Generado', $generado],
            ['Registros', count($informe['filas'])],
            [null, null],
            ['Indicador', 'Valor'],
            ['Llegadas temprano', $kpis['total']],
            ['Promedio por marca', LlegadaTardeService::minutosLabel($kpis['promedio'])],
            ['Empleados', $kpis['empleados']],
        ], null, 'A2');

        $hoja->getStyle('A7:B7')->getFont()->setBold(true);
        $hoja->getStyle('A7:B7')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E3A5F');
        $hoja->getStyle('A7:B7')->getFont()->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A7:B10')->applyFromArray($this->bordes());

        $hoja->setCellValue('A12', 'Ranking · 6 con más llegadas temprano');
        $hoja->mergeCells('A12:E12');
        $hoja->getStyle('A12')->getFont()->setBold(true)->setSize(13);

        $hoja->fromArray([
            ['Puesto', 'Empleado', 'Cédula', 'Cargo', 'Llegadas temprano'],
        ], null, 'A13');

        $datos = [];
        foreach ($informe['ranking'] as $fila) {
            $datos[] = [
                $fila['puesto'],
                $fila['nombre'],
                $fila['identificacion'],
                $fila['cargo'],
                $fila['veces'],
            ];
        }
        if ($datos !== []) {
            $hoja->fromArray($datos, null, 'A14');
        }

        $ultima = max(14, 13 + count($datos));
        $hoja->getStyle('A13:E13')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A13:E13')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E3A5F');
        $hoja->getStyle('A13:E'.$ultima)->applyFromArray($this->bordes());

        foreach (range('A', 'E') as $col) {
            $hoja->getColumnDimension($col)->setAutoSize(true);
        }
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
            'Jornada',
            'Programada',
            'Marcó',
            'Anticipación',
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
                $fila['jornada'],
                $fila['entrada'],
                $fila['marco'],
                $fila['temprano_label'],
            ];
        }
        if ($datos !== []) {
            $hoja->fromArray($datos, null, 'A2');
        }

        $ultima = max(2, count($datos) + 1);
        $rango = 'A1:J'.$ultima;
        $hoja->getStyle('A1:J1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A1:J1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E3A5F');
        $hoja->getStyle('A1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle($rango)->applyFromArray($this->bordes());
        $hoja->setAutoFilter($rango);
        $hoja->freezePane('A2');
        $hoja->getRowDimension(1)->setRowHeight(22);

        foreach (range('A', 'J') as $col) {
            $hoja->getColumnDimension($col)->setAutoSize(true);
        }

        $hoja->getHeaderFooter()->setOddHeader('&CRegistros de Puntualidad · '.$mesLabel.' · todos los empleados');
        $hoja->getHeaderFooter()->setOddFooter('&LControl de acceso&RPágina &P de &N');
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
