<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document PDF Rendering
    |--------------------------------------------------------------------------
    |
    | chromium: generate a real PDF through Browsershot/Chromium.
    | html: return the printable HTML view directly and let the browser print/save.
    |
    */
    'pdf_renderer' => env('DOCUMENT_PDF_RENDERER', 'chromium'),

    /*
    |--------------------------------------------------------------------------
    | PDF Browser Disposition
    |--------------------------------------------------------------------------
    |
    | inline opens Chromium-generated PDFs in the browser.
    | attachment asks the browser to download the generated PDF.
    |
    */
    'pdf_disposition' => env('DOCUMENT_PDF_DISPOSITION', 'inline'),
];
