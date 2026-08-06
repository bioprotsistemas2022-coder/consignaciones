<?php
function rutaWeb($rutaAbsoluta) {
    $base = realpath(__DIR__ . '/..');
    $relativa = str_replace($base, '', realpath($rutaAbsoluta));
    $partes = array_values(array_filter(explode(DIRECTORY_SEPARATOR, trim($relativa, DIRECTORY_SEPARATOR))));
    $encoded = array_map('rawurlencode', $partes);
    return implode('/', $encoded);
}

function listarPDFs($directorioBase) {
    $pdfsDir = $directorioBase . DIRECTORY_SEPARATOR . 'pdfs';
    if (!is_dir($pdfsDir)) return [];
    $skipDir = ['EXCEL NUEVOS'];
    $categorias = [];
    $dirIt = new RecursiveDirectoryIterator($pdfsDir, RecursiveDirectoryIterator::SKIP_DOTS);
    $it = new RecursiveIteratorIterator($dirIt, RecursiveIteratorIterator::SELF_FIRST);

    $pdfsPorDir = [];
    foreach ($it as $fileinfo) {
        if ($fileinfo->isDir()) continue;
        if (strtolower($fileinfo->getExtension()) !== 'pdf') continue;

        $parentDir = $fileinfo->getPathInfo()->getFilename();
        $parentPath = $fileinfo->getPathInfo()->getPathname();

        $skip = false;
        $rel = DIRECTORY_SEPARATOR . trim(str_replace($pdfsDir, '', $parentPath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        foreach ($skipDir as $sd) {
            if (stripos($rel, DIRECTORY_SEPARATOR . $sd . DIRECTORY_SEPARATOR) !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;

        $pdfsPorDir[$parentDir][] = $fileinfo;
    }

    foreach ($pdfsPorDir as $nombre => $archivos) {
        $pdfs = [];
        foreach ($archivos as $archivo) {
            $pdfs[] = [
                'nombre' => $archivo->getFilename(),
                'ruta_web' => rutaWeb($archivo->getPathname()),
                'tamano' => $archivo->getSize()
            ];
        }
        usort($pdfs, function ($a, $b) { return strcasecmp($a['nombre'], $b['nombre']); });
        $categorias[] = ['nombre' => $nombre, 'pdfs' => $pdfs];
    }

    usort($categorias, function ($a, $b) { return strcasecmp($a['nombre'], $b['nombre']); });
    return $categorias;
}
