<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\File;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PdfRenderer
{
    public function htmlResponse(string $html, string $fileName): SymfonyResponse
    {
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    public function fileResponse(string $path, string $fileName): SymfonyResponse
    {
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $this->disposition().' filename="'.$fileName.'"',
        ]);
    }

    public function renderA4(string $html, string $path, string $footerHtml): void
    {
        File::ensureDirectoryExists(dirname($path));

        $tmpDirectory = storage_path('app/tmp');
        File::ensureDirectoryExists($tmpDirectory);
        File::ensureDirectoryExists($tmpDirectory.DIRECTORY_SEPARATOR.'chromium-profile');

        Browsershot::html($html)
            ->setNodeBinary(config('services.browsershot.node_binary', '/usr/bin/node'))
            ->setChromePath(config('services.browsershot.chrome_path', '/usr/bin/chromium'))
            ->setUserDataDir($tmpDirectory.DIRECTORY_SEPARATOR.'chromium-profile')
            ->setEnvironmentOptions([
                'HOME' => '/tmp',
                'XDG_CACHE_HOME' => '/tmp',
                'XDG_CONFIG_HOME' => '/tmp',
            ])
            ->noSandbox()
            ->showBackground()
            ->showBrowserHeaderAndFooter()
            ->hideHeader()
            ->footerHtml($footerHtml)
            ->format('A4')
            ->margins(14, 12, 22, 12)
            ->timeout(180)
            ->protocolTimeout(180)
            ->addChromiumArguments([
                'disable-crash-reporter',
                'disable-crashpad',
                'disable-dev-shm-usage',
                'disable-gpu',
                'disable-setuid-sandbox',
                'no-zygote',
            ])
            ->save($path);
    }

    private function disposition(): string
    {
        return config('documents.pdf_disposition') === 'attachment' ? 'attachment;' : 'inline;';
    }
}
