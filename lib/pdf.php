<?php
/* ============================================================
   FV_PDF — Generador de PDF (A4) sin dependencias externas.
   Estilo: cabeceras de color, tablas, texto con ajuste y logo
   (PNG/JPEG vía GD, incrustado como JPEG/DCTDecode).
   Sólo soporta una página; suficiente para facturas, recibos y
   cartas. Unidades: milímetros, origen en la esquina superior
   izquierda (la conversión a coordenadas PDF es automática).
   ============================================================ */

final class FV_PDF
{
    /** @var string[] Contenido por página. */
    private array $paginas = [''];
    private int $pag = 0;

    /** @var array{data:string,w:int,h:int}[] Imágenes incrustadas. */
    private array $imgs = [];
    /** @var array<int,string[]> Imágenes usadas por página. */
    private array $imgUsadas = [[]];

    private string $tipoFuente = 'regular'; // regular | bold
    private float $tamFuente = 11;

    public float $ancho = 210;
    public float $alto = 297;
    public float $margenY = 286; // máximo Y de contenido

    /* ---------- Utilidades de texto ---------- */

    private static function limpiar(string $t): string
    {
        if (function_exists('mb_convert_encoding')) {
            $t = mb_convert_encoding($t, 'CP1252', 'UTF-8');
        } else {
            $t = @utf8_decode($t);
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $t);
    }

    private static function anchoCaracter(string $ch, string $tipo): float
    {
        $w = [ // factores aproximados Helvetica (unidad = tamaño de fuente)
            ' ' => 0.28, 'i' => 0.28, 'j' => 0.28, 'l' => 0.28, 't' => 0.30, 'f' => 0.30,
            'r' => 0.32, 's' => 0.34, 'n' => 0.50, 'm' => 0.78, 'w' => 0.72,
            '0' => 0.50, '1' => 0.50, '2' => 0.50, '3' => 0.50, '4' => 0.50,
            '5' => 0.50, '6' => 0.50, '7' => 0.50, '8' => 0.50, '9' => 0.50,
        ];
        return ($w[$ch] ?? 0.50) * ($tipo === 'bold' ? 1.03 : 1.0);
    }

    public function anchoTexto(string $t, ?string $tipo = null, ?float $tam = null): float
    {
        $tipo ??= $this->tipoFuente;
        $tam ??= $this->tamFuente;
        $ancho = 0.0;
        foreach (preg_split('//u', $t, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $ancho += self::anchoCaracter(strtolower($ch), $tipo);
        }
        return $ancho * $tam;
    }

    public function ajustarTexto(string $t, float $anchoMax, ?string $tipo = null, ?float $tam = null): array
    {
        $tipo ??= $this->tipoFuente;
        $tam ??= $this->tamFuente;
        $palabras = preg_split('/\s+/u', trim($t)) ?: [];
        $lineas = [];
        $actual = '';
        foreach ($palabras as $p) {
            $candidata = $actual === '' ? $p : $actual . ' ' . $p;
            if ($this->anchoTexto($candidata, $tipo, $tam) <= $anchoMax || $actual === '') {
                $actual = $candidata;
            } else {
                $lineas[] = $actual;
                $actual = $p;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }
        return $lineas;
    }

    /* ---------- Estado ---------- */

    public function nuevaPagina(): void
    {
        $this->paginas[] = '';
        $this->imgUsadas[] = [];
        $this->pag = count($this->paginas) - 1;
    }

    public function fuente(string $tipo, float $tam): void
    {
        $this->tipoFuente = in_array($tipo, ['regular', 'bold'], true) ? $tipo : 'regular';
        $this->tamFuente = max(4, $tam);
    }

    /* ---------- Trazos ---------- */

    public function lineaT(float $x1, float $y1, float $x2, float $y2, array $color = [120, 135, 160], float $grosor = 0.3): void
    {
        $yPdf1 = $this->alto - $y1;
        $yPdf2 = $this->alto - $y2;
        $this->paginas[$this->pag] .= sprintf('%s %s rg %.3f w %.2f %.2f m %.2f %.2f l S',
            $color[0] / 255, $color[1] / 255, $color[2] / 255, $grosor, $x1, $yPdf1, $x2, $yPdf2) . " \n";
    }

    public function rectT(float $x, float $y, float $w, float $h, array $opts = []): void
    {
        $yPdf = $this->alto - $y - $h;
        $fill = $opts['fill'] ?? null;
        $stroke = $opts['stroke'] ?? null;
        $comando = $fill ? sprintf('%s %s %s rg %.2f %.2f %.2f %.2f re f',
            $fill[0] / 255, $fill[1] / 255, $fill[2] / 255, $x, $yPdf, $w, $h) : '';
        if ($stroke) {
            $comando .= sprintf(' %s %s %s RG %.3f w %.2f %.2f %.2f %.2f re S',
                $stroke[0] / 255, $stroke[1] / 255, $stroke[2] / 255,
                $opts['ancho'] ?? 0.5, $x, $yPdf, $w, $h);
        }
        if ($comando !== '') {
            $this->paginas[$this->pag] .= trim($comando) . " \n";
        }
    }

    /* ---------- Texto ---------- */

    public function textoT(float $x, float $y, string $t, array $opts = []): float
    {
        $tipo = (string)($opts['tipo'] ?? $this->tipoFuente);
        $tam = (float)($opts['tam'] ?? $this->tamFuente);
        $color = $opts['color'] ?? [25, 25, 25];
        $align = (string)($opts['align'] ?? 'left');
        $anchoMax = (float)($opts['ancho'] ?? 0);

        $lineas = $anchoMax > 0 ? $this->ajustarTexto($t, $anchoMax, $tipo, $tam) : [$t];
        $altoLinea = $tam * 0.352778 * 1.25;
        foreach ($lineas as $i => $linea) {
            $yLine = $y + $i * $altoLinea;
            $yPdf = $this->alto - $yLine;
            $w = $this->anchoTexto($linea, $tipo, $tam);
            $x0 = $x;
            if ($align === 'right') {
                $x0 = $x - $w;
            } elseif ($align === 'center') {
                $x0 = $x - $w / 2;
            }
            $font = $tipo === 'bold' ? 'F2' : 'F1';
            $this->paginas[$this->pag] .= sprintf('BT /%s %.3f Tf %s %s %s rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET',
                $font, $tam, $color[0] / 255, $color[1] / 255, $color[2] / 255, $x0, $yPdf, self::limpiar($linea)) . " \n";
        }
        return $y + count($lineas) * $altoLinea;
    }

    /* ---------- Imagen (logo) ---------- */

    public function imagenT(string $rutaArchivo, float $x, float $y, float $w, float $h): bool
    {
        $datos = $this->imagenDatos($rutaArchivo);
        if ($datos === null) {
            return false;
        }
        $clave = 'im' . count($this->imgs);
        $this->imgs[$clave] = $datos;
        $this->imgUsadas[$this->pag][] = $clave;

        $w /= 25.4; // mm -> pts
        $h /= 25.4;
        $xPts = $x / 25.4;
        $yPts = ($this->alto - $y - ($h * 25.4)) / 25.4;
        $this->paginas[$this->pag] .= sprintf("q %.5f 0 0 %.5f %.5f %.5f cm /%s Do Q\n", $w, $h, $xPts, $yPts, $clave);
        return true;
    }

    /**
     * Lee un PNG o JPEG y devuelve los datos listos para incrustar en el PDF.
     * Si GD está disponible convierte la imagen a JPEG (DCTDecode); si no,
     * se decodifica el PNG a RGB plano con código puro de PHP (FlateDecode).
     * Resultado: ['data', 'w', 'h', 'kind'] con kind 'jpeg' | 'rgb'.
     */
    private function imagenDatos(string $ruta): ?array
    {
        if (!is_file($ruta)) {
            return null;
        }
        $datos = @file_get_contents($ruta);
        if ($datos === false) {
            return null;
        }
        if (function_exists('imagecreatefromstring')) {
            $im = @imagecreatefromstring($datos);
            if ($im !== false) {
                $w = imagesx($im);
                $h = imagesy($im);
                $canvas = imagecreatetruecolor($w, $h);
                $blanco = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $blanco);
                imagecopy($canvas, $im, 0, 0, 0, 0, $w, $h);
                ob_start();
                imagejpeg($canvas, null, 92);
                $jpeg = ob_get_clean();
                imagedestroy($im);
                imagedestroy($canvas);
                if ($jpeg !== false && $jpeg !== '') {
                    return ['data' => $jpeg, 'w' => $w, 'h' => $h, 'kind' => 'jpeg'];
                }
            }
        }
        return $this->pngRGB($datos);
    }

    /** Decodifica un PNG de 8 bits (no interlazado) a RGB plano sin GD. */
    private function pngRGB(string $datos): ?array
    {
        if (substr($datos, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }
        $pos = 8;
        $fin = strlen($datos);
        $ihdr = null;
        $idat = '';
        $plte = '';
        $trns = '';
        while ($pos + 8 <= $fin) {
            $tam = unpack('N', substr($datos, $pos, 4))[1];
            $tipo = substr($datos, $pos + 4, 4);
            $dat = substr($datos, $pos + 8, $tam);
            if ($tipo === 'IHDR') {
                $ihdr = unpack('Nw/Nh/Cbit/Ccolor/Ccomp/Cfilt/Cinter', $dat);
            } elseif ($tipo === 'IDAT') {
                $idat .= $dat;
            } elseif ($tipo === 'PLTE') {
                $plte = $dat;
            } elseif ($tipo === 'tRNS') {
                $trns = $dat;
            }
            $pos += 12 + $tam;
            if ($tipo === 'IEND') {
                break;
            }
        }
        if ($ihdr === null || $ihdr['bit'] !== 8 || $ihdr['comp'] !== 0 || $ihdr['filt'] !== 0 || $ihdr['inter'] !== 0) {
            return null;
        }
        $w = $ihdr['w'];
        $h = $ihdr['h'];
        $color = $ihdr['color'];
        if ($w < 1 || $w > 5000 || $h < 1 || $h > 5000) {
            return null;
        }
        $canales = [0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4][$color] ?? null;
        if ($canales === null) {
            return null;
        }
        $bpp = $canales; // bitDepth 8 => bytes por pixel igual a canales
        $raw = @gzuncompress($idat);
        if ($raw === false || strlen($raw) < $h * (1 + $w * $bpp)) {
            return null;
        }
        // Deshacer filtros PNG fila por fila (tipos 0..4).
        $filas = [];
        $prev = '';
        $off = 0;
        $anchoBytes = $w * $bpp;
        for ($y = 0; $y < $h; $y++) {
            $filt = ord($raw[$off]);
            $off++;
            $fila = substr($raw, $off, $anchoBytes);
            $off += $anchoBytes;
            if ($filt >= 5) {
                return null;
            }
            $dec = '';
            for ($x = 0; $x < $anchoBytes; $x++) {
                $v = ord($fila[$x]);
                $a = $x >= $bpp ? ord($dec[$x - $bpp]) : 0;
                $b = $y > 0 ? ord($prev[$x]) : 0;
                $c = ($x >= $bpp && $y > 0) ? ord($prev[$x - $bpp]) : 0;
                switch ($filt) {
                    case 1: $v = ($v + $a) & 0xff; break;
                    case 2: $v = ($v + $b) & 0xff; break;
                    case 3: $v = ($v + (int)(($a + $b) / 2)) & 0xff; break;
                    case 4:
                        $p = $a + $b - $c;
                        $pa = abs($p - $a);
                        $pb = abs($p - $b);
                        $pc = abs($p - $c);
                        $pr = ($pa <= $pb && $pa <= $pc) ? $a : (($pb <= $pc) ? $b : $c);
                        $v = ($v + $pr) & 0xff;
                        break;
                }
                $dec .= chr($v);
            }
            $filas[] = $dec;
            $prev = $dec;
        }
        // En PNGs paletizados (color 3) sin PLTE se usa una paleta de 8 grises.
        $paleta = [];
        $alpha = [];
        if ($color === 3) {
            if ($plte === '') {
                $n = 2;
                for ($i = 0; $i < $n; $i++) {
                    $g = (int)round($i * 255 / max(1, $n - 1));
                    $paleta[] = [$g, $g, $g];
                    $alpha[] = 255;
                }
            } else {
                $n = (int)(strlen($plte) / 3);
                for ($i = 0; $i < $n; $i++) {
                    $paleta[] = [ord($plte[$i * 3]), ord($plte[$i * 3 + 1]), ord($plte[$i * 3 + 2])];
                    $alpha[] = 255;
                }
            }
            if ($trns !== '') {
                $largoTrns = min(strlen($trns), count($alpha));
                for ($i = 0; $i < $largoTrns; $i++) {
                    $alpha[$i] = ord($trns[$i]);
                }
            }
        }
        $rgb = '';
        foreach ($filas as $fila) {
            if ($color === 0) { // escala de grises
                for ($i = 0; $i < $w; $i++) {
                    $g = ord($fila[$i]);
                    $rgb .= chr($g) . chr($g) . chr($g);
                }
            } elseif ($color === 4) { // gris + alpha
                for ($i = 0; $i < $w; $i++) {
                    $g = ord($fila[$i * 2]);
                    $al = ord($fila[$i * 2 + 1]);
                    $v = ($g * $al + 255 * (255 - $al)) >> 8;
                    $rgb .= chr($v) . chr($v) . chr($v);
                }
            } elseif ($color === 3) { // paletizado
                for ($i = 0; $i < $w; $i++) {
                    $e = ord($fila[$i]);
                    $al = $alpha[$e] ?? 255;
                    $p = $paleta[$e] ?? [0, 0, 0];
                    $r = max(0, min(255, ($p[0] * $al + 255 * (255 - $al)) >> 8));
                    $g = max(0, min(255, ($p[1] * $al + 255 * (255 - $al)) >> 8));
                    $b = max(0, min(255, ($p[2] * $al + 255 * (255 - $al)) >> 8));
                    $rgb .= chr($r) . chr($g) . chr($b);
                }
            } elseif ($color === 6) { // RGBA sobre blanco
                for ($i = 0; $i < $w; $i++) {
                    $r = ord($fila[$i * 4]);
                    $g = ord($fila[$i * 4 + 1]);
                    $b = ord($fila[$i * 4 + 2]);
                    $al = ord($fila[$i * 4 + 3]);
                    $r = max(0, min(255, ($r * $al + 255 * (255 - $al)) >> 8));
                    $g = max(0, min(255, ($g * $al + 255 * (255 - $al)) >> 8));
                    $b = max(0, min(255, ($b * $al + 255 * (255 - $al)) >> 8));
                    $rgb .= chr($r) . chr($g) . chr($b);
                }
            } else { // RGB directo
                $rgb .= $fila;
            }
        }
        $comprimido = gzcompress($rgb, 9);
        if ($comprimido === false) {
            return null;
        }
        return ['data' => $comprimido, 'w' => $w, 'h' => $h, 'kind' => 'rgb'];
    }

    /* ---------- Ensamblado del archivo PDF ---------- */

    public function bytes(): string
    {
        $nPaginas = count($this->paginas);

        // Objetos base a imagen
        $nImagenes = count($this->imgs);
        $plan = ['catalogo', 'paginas'];
        for ($i = 0; $i < $nPaginas; $i++) {
            $plan[] = 'pagina';
        }
        $plan[] = 'font1';
        $plan[] = 'font2';
        foreach (array_keys($this->imgs) as $k) {
            $plan[] = 'img:' . $k;
        }
        $inicioContenido = 4 + $nPaginas + $nImagenes; // índice (0-based) del primer objeto 'contenido'
        for ($i = 0; $i < $nPaginas; $i++) {
            $plan[] = 'contenido';
        }

        $numObjetos = count($plan);
        $cuerpo = '';
        $offsets = [];
        for ($i = 0; $i < $numObjetos; $i++) {
            $offsets[] = strlen($cuerpo);
            $nobj = $i + 1;
            $tabla = $plan[$i];
            $s = $nobj . " 0 obj\n";
            if ($tabla === 'catalogo') {
                $s .= "<< /Type /Catalog /Pages 2 0 R >>\n";
            } elseif ($tabla === 'paginas') {
                $kids = implode(' ', array_map(fn($k) => ($k + 3) . ' 0 R', range(0, $nPaginas - 1)));
                $s .= "<< /Type /Pages /Kids [$kids] /Count $nPaginas >>\n";
            } elseif ($tabla === 'pagina') {
                $idx = $i - 2; // índice de página (0-based)
                $font1Obj = 3 + $nPaginas;
                $font2Obj = 4 + $nPaginas;
                $res = '/Font << /F1 ' . $font1Obj . ' 0 R /F2 ' . $font2Obj . ' 0 R >>';
                if (!empty($this->imgUsadas[$idx])) {
                    $archivos = [];
                    foreach ($this->imgUsadas[$idx] as $k) {
                        $numImg = 5 + $nPaginas + (int)substr($k, 2);
                        $archivos[] = '/' . $k . ' ' . $numImg . ' 0 R';
                    }
                    $res .= ' /XObject << ' . implode(' ', $archivos) . ' >>';
                }
                $contenidoObj = count($plan) - $nPaginas + $idx + 1;
                $s .= "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << $res >> /Contents $contenidoObj 0 R >>\n";
            } elseif ($tabla === 'font1') {
                $s .= "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\n";
            } elseif ($tabla === 'font2') {
                $s .= "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\n";
            } elseif (str_starts_with($tabla, 'img:')) {
                $img = $this->imgs[substr($tabla, 4)];
                $filtro = ($img['kind'] ?? 'jpeg') === 'rgb' ? '/Filter /FlateDecode' : '/Filter /DCTDecode';
                $s .= "<< /Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 $filtro /Length " . strlen($img['data']) . " >>\nstream\n" . $img['data'] . "\nendstream\n";
            } else { // contenido
                $idx = $i - $inicioContenido;
                $stream = $this->paginas[$idx];
                $comprimido = gzcompress($stream, 9);
                $s .= "<< /Length " . strlen($comprimido) . " /Filter /FlateDecode >>\nstream\n" . $comprimido . "\nendstream\n";
            }
            $s .= "endobj\n";
            $cuerpo .= $s;
        }

        $offsetsEntradas = $offsets;
        $xrefPos = strlen($cuerpo);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n" . $cuerpo . "xref\n0 " . ($numObjetos + 1) . "\n0000000000 65535 f \n";
        foreach ($offsetsEntradas as $off) {
            $out .= sprintf("%010d 00000 n \n", $off);
        }
        $out .= "trailer\n<< /Size " . ($numObjetos + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";
        return $out;
    }

    public function descargar(string $nombreArchivo = 'documento.pdf', bool $adjunto = true): never
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($adjunto ? 'attachment' : 'inline') . '; filename="' . str_replace(['"', "\r", "\n"], '', $nombreArchivo) . '"');
        header('Content-Length: ' . strlen($this->bytes()));
        echo $this->bytes();
        exit;
    }
}