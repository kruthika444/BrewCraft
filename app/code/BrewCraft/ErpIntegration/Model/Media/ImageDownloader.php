<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Media;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\HTTP\Client\Curl;

class ImageDownloader
{
    private const IMPORT_DIRECTORY = 'import/brewcraft_erp_media';

    private WriteInterface $varDirectory;

    public function __construct(
        Filesystem $filesystem,
        private readonly Curl $curl
    ) {
        $this->varDirectory = $filesystem->getDirectoryWrite(
            DirectoryList::VAR_DIR
        );
    }

    /**
     * Download an ERP image into Magento var/import.
     *
     * Returns the absolute local file path.
     */
    public function download(
        string $url,
        string $filename
    ): string {
        $this->validateUrl($url);

        $filename = $this->sanitizeFilename(
            $filename
        );

        if ($filename === '') {
            throw new LocalizedException(
                __('Unable to generate a valid ERP media filename.')
            );
        }

        $this->varDirectory->create(
            self::IMPORT_DIRECTORY
        );

        $relativePath = sprintf(
            '%s/%s',
            self::IMPORT_DIRECTORY,
            $filename
        );

        try {
            $this->curl->setTimeout(30);

            $this->curl->get($url);
        } catch (\Throwable $exception) {
            throw new LocalizedException(
                __(
                    'Unable to download ERP image "%1": %2',
                    $url,
                    $exception->getMessage()
                )
            );
        }

        $statusCode = $this->curl->getStatus();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new LocalizedException(
                __(
                    'ERP image "%1" returned HTTP status %2.',
                    $url,
                    $statusCode
                )
            );
        }

        $body = $this->curl->getBody();

        if ($body === '') {
            throw new LocalizedException(
                __(
                    'ERP image "%1" returned an empty response.',
                    $url
                )
            );
        }

        $this->varDirectory->writeFile(
            $relativePath,
            $body
        );

        $absolutePath = $this->varDirectory
            ->getAbsolutePath($relativePath);

        /*
         * Ensure the downloaded file is actually an image.
         */
        $imageInfo = @getimagesize(
            $absolutePath
        );

        if ($imageInfo === false) {
            $this->varDirectory->delete(
                $relativePath
            );

            throw new LocalizedException(
                __(
                    'ERP media URL "%1" did not return a valid image.',
                    $url
                )
            );
        }

        return $absolutePath;
    }

    private function validateUrl(
        string $url
    ): void {
        $parts = parse_url($url);

        if (
            $parts === false
            || empty($parts['scheme'])
            || empty($parts['host'])
            || !in_array(
                strtolower((string)$parts['scheme']),
                ['http', 'https'],
                true
            )
        ) {
            throw new LocalizedException(
                __(
                    'Invalid ERP image URL "%1".',
                    $url
                )
            );
        }
    }

    private function sanitizeFilename(
        string $filename
    ): string {
        $filename = basename($filename);

        return (string)preg_replace(
            '/[^a-zA-Z0-9._-]/',
            '-',
            $filename
        );
    }
}
