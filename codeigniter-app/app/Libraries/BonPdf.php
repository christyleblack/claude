<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Rendu PDF d'un bon (STK-06) avec Dompdf, à partir de la vue stocks/bons/pdf.
 * Aucune ressource externe n'est chargée (polices et styles intégrés).
 */
class BonPdf
{
    public static function rendre(array $bon, array $lignes): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('stocks/bons/pdf', ['bon' => $bon, 'lignes' => $lignes]));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
