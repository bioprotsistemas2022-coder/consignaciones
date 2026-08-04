<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'Smalot\\PdfParser\\') === 0) {
        $file = __DIR__ . '/../vendor/pdfparser/src/' . str_replace('\\', '/', $class) . '.php';
        if (is_file($file)) require $file;
    }
});
