<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Media;

use BrewCraft\ErpIntegration\Logger\Logger;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;

class CategoryImageImporter
{
    private const CATEGORY_DIRECTORY = 'catalog/category/erp';

    private WriteInterface $mediaDirectory;

    public function __construct(
        private readonly ImageDownloader $imageDownloader,
        Filesystem $filesystem,
        private readonly Logger $logger
    ) {
        $this->mediaDirectory = $filesystem
            ->getDirectoryWrite(
                DirectoryList::MEDIA
            );
    }

    public function import(
        Category $category,
        array $erpCategory
    ): bool {
        $url = trim(
            (string)($erpCategory['image_url'] ?? '')
        );

        if ($url === '') {
            return false;
        }

        try {
            $filename = $this->buildFilename(
                (string)$erpCategory['code'],
                $url
            );

            $relativeFile = sprintf(
                '%s/%s',
                self::CATEGORY_DIRECTORY,
                $filename
            );

            /*
             * Same ERP category image already assigned.
             */
            if (
                (string)$category->getImage()
                === 'erp/' . $filename
            ) {
                return false;
            }

            $temporaryFile = $this->imageDownloader
                ->download(
                    $url,
                    $filename
                );

            $this->mediaDirectory->create(
                self::CATEGORY_DIRECTORY
            );

            $contents = file_get_contents(
                $temporaryFile
            );

            if ($contents === false) {
                throw new \RuntimeException(
                    sprintf(
                        'Unable to read downloaded category image "%s".',
                        $temporaryFile
                    )
                );
            }

            $this->mediaDirectory->writeFile(
                $relativeFile,
                $contents
            );

            /*
             * Category image value is relative to:
             *
             * pub/media/catalog/category/
             */
            $category->setImage(
                'erp/' . $filename
            );

            $this->logger->info(
                sprintf(
                    '[ERP MEDIA] Category image synchronized. Code: "%s", URL: "%s".',
                    $erpCategory['code'],
                    $url
                )
            );

            return true;
        } catch (\Throwable $exception) {
            /*
             * Same policy as product media:
             *
             * A broken image should not break category hierarchy sync.
             */
            $this->logger->error(
                sprintf(
                    '[ERP MEDIA] Failed category image sync. Code: "%s", URL: "%s", Error: %s',
                    $erpCategory['code'] ?? 'UNKNOWN',
                    $url,
                    $exception->getMessage()
                )
            );

            return false;
        }
    }

    private function buildFilename(
        string $code,
        string $url
    ): string {
        $path = parse_url(
            $url,
            PHP_URL_PATH
        );

        $originalName = basename(
            is_string($path)
                ? $path
                : 'category.jpg'
        );

        if ($originalName === '') {
            $originalName = 'category.jpg';
        }

        $safeCode = strtolower(
            (string)preg_replace(
                '/[^a-zA-Z0-9_-]/',
                '-',
                $code
            )
        );

        $safeOriginalName = strtolower(
            (string)preg_replace(
                '/[^a-zA-Z0-9._-]/',
                '-',
                $originalName
            )
        );

        return sprintf(
            '%s-erp-%s-%s',
            $safeCode,
            substr(sha1($url), 0, 8),
            $safeOriginalName
        );
    }
}
