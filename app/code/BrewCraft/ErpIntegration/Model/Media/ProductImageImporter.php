<?php

declare(strict_types=1);

namespace BrewCraft\ErpIntegration\Model\Media;

use BrewCraft\ErpIntegration\Logger\Logger;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\Processor as GalleryProcessor;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;

class ProductImageImporter
{
    private const VALID_ROLES = [
        'image',
        'small_image',
        'thumbnail'
    ];

    /**
     * Temporary media directory under pub/media.
     *
     * Product media gallery processor expects the source file
     * to be inside Magento's media filesystem.
     */
    private const MEDIA_IMPORT_DIRECTORY =
        'import/brewcraft_erp_media';

    private WriteInterface $mediaDirectory;

    public function __construct(
        private readonly ImageDownloader $imageDownloader,
        private readonly GalleryProcessor $galleryProcessor,
        Filesystem $filesystem,
        private readonly Logger $logger
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(
            DirectoryList::MEDIA
        );
    }

    public function import(
        Product $product,
        array $erpProduct
    ): bool {
        $images = $erpProduct['images'] ?? [];

        if (
            !is_array($images)
            || empty($images)
        ) {
            return false;
        }

        $changed = false;

        foreach ($images as $index => $imageData) {
            try {
                if (
                    empty($imageData['url'])
                    || !is_string($imageData['url'])
                ) {
                    $this->logger->warning(
                        sprintf(
                            '[ERP MEDIA] Missing image URL for SKU "%s".',
                            $product->getSku()
                        )
                    );

                    continue;
                }

                $url = trim(
                    (string)$imageData['url']
                );

                $position = isset(
                    $imageData['position']
                )
                    ? (int)$imageData['position']
                    : ($index + 1);

                $roles = $this->normalizeRoles(
                    $imageData['roles'] ?? []
                );

                $filename = $this->buildFilename(
                    (string)$product->getSku(),
                    $position,
                    $url
                );

                /*
                 * Has this exact ERP image already been added
                 * to the Magento product gallery?
                 */
                $existingFile = $this->findExistingFile(
                    $product,
                    $filename
                );

                if ($existingFile !== null) {
                    $this->assignRoles(
                        $product,
                        $existingFile,
                        $roles
                    );

                    continue;
                }

                /*
                 * If ERP changed the image URL for this
                 * SKU + position, remove the older ERP-managed
                 * gallery image.
                 */
                $this->removePreviousVersion(
                    $product,
                    $position,
                    $filename
                );

                /*
                 * STEP 1
                 *
                 * Download from ERP into:
                 *
                 * var/import/brewcraft_erp_media/
                 */
                $downloadedFile = $this->imageDownloader
                    ->download(
                        $url,
                        $filename
                    );

                /*
                 * STEP 2
                 *
                 * Copy the downloaded image into:
                 *
                 * pub/media/import/brewcraft_erp_media/
                 *
                 * The product gallery processor can safely
                 * work with a file inside Magento's media
                 * filesystem.
                 */
                $mediaImportFile =
                    $this->copyToMediaImportDirectory(
                        $downloadedFile,
                        $filename
                    );

                $disabled = (bool)(
                    $imageData['disabled']
                    ?? false
                );

                /*
                 * STEP 3
                 *
                 * Magento now receives a source file that lives
                 * inside pub/media.
                 */
                $product->addImageToMediaGallery(
                    $mediaImportFile,
                    $roles,
                    false,
                    $disabled
                );

                $changed = true;

                $this->logger->info(
                    sprintf(
                        '[ERP MEDIA] Product image synchronized. SKU: "%s", Position: %d, URL: "%s".',
                        $product->getSku(),
                        $position,
                        $url
                    )
                );
            } catch (\Throwable $exception) {
                /*
                 * One broken image must not fail the entire
                 * ERP product synchronization.
                 */
                $this->logger->error(
                    sprintf(
                        '[ERP MEDIA] Failed product image sync. SKU: "%s", URL: "%s", Error: %s',
                        $product->getSku(),
                        $imageData['url'] ?? 'UNKNOWN',
                        $exception->getMessage()
                    )
                );
            }
        }

        return $changed;
    }

    /**
     * Copy var/import image into pub/media/import before
     * passing it to Magento's media gallery processor.
     */
    private function copyToMediaImportDirectory(
        string $downloadedFile,
        string $filename
    ): string {
        if (!is_file($downloadedFile)) {
            throw new \RuntimeException(
                sprintf(
                    'Downloaded ERP image "%s" does not exist.',
                    $downloadedFile
                )
            );
        }

        $contents = file_get_contents(
            $downloadedFile
        );

        if ($contents === false) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to read downloaded ERP image "%s".',
                    $downloadedFile
                )
            );
        }

        $this->mediaDirectory->create(
            self::MEDIA_IMPORT_DIRECTORY
        );

        $relativePath = sprintf(
            '%s/%s',
            self::MEDIA_IMPORT_DIRECTORY,
            $filename
        );

        $this->mediaDirectory->writeFile(
            $relativePath,
            $contents
        );

        return $this->mediaDirectory
            ->getAbsolutePath(
                $relativePath
            );
    }

    private function normalizeRoles(
        mixed $roles
    ): array {
        if (!is_array($roles)) {
            return [];
        }

        return array_values(
            array_intersect(
                $roles,
                self::VALID_ROLES
            )
        );
    }

    private function buildFilename(
        string $sku,
        int $position,
        string $url
    ): string {
        $urlPath = parse_url(
            $url,
            PHP_URL_PATH
        );

        $originalName = basename(
            is_string($urlPath)
                ? $urlPath
                : 'image.jpg'
        );

        if ($originalName === '') {
            $originalName = 'image.jpg';
        }

        $safeSku = strtolower(
            (string)preg_replace(
                '/[^a-zA-Z0-9_-]/',
                '-',
                $sku
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
            '%s-erp-%d-%s-%s',
            $safeSku,
            $position,
            substr(
                sha1($url),
                0,
                8
            ),
            $safeOriginalName
        );
    }

    private function findExistingFile(
        Product $product,
        string $filename
    ): ?string {
        $entries = $product
            ->getMediaGalleryEntries();

        if (!is_array($entries)) {
            return null;
        }

        foreach ($entries as $entry) {
            $file = (string)$entry->getFile();

            /*
             * Magento can prepend hash-based directories,
             * therefore compare only the final basename.
             */
            if (
                basename($file)
                === $filename
            ) {
                return $file;
            }
        }

        return null;
    }

    private function removePreviousVersion(
        Product $product,
        int $position,
        string $newFilename
    ): void {
        $entries = $product
            ->getMediaGalleryEntries();

        if (!is_array($entries)) {
            return;
        }

        $safeSku = strtolower(
            (string)preg_replace(
                '/[^a-zA-Z0-9_-]/',
                '-',
                (string)$product->getSku()
            )
        );

        $prefix = sprintf(
            '%s-erp-%d-',
            $safeSku,
            $position
        );

        foreach ($entries as $entry) {
            $file = (string)$entry->getFile();

            $basename = basename(
                $file
            );

            if (
                str_starts_with(
                    $basename,
                    $prefix
                )
                && $basename !== $newFilename
            ) {
                $this->galleryProcessor
                    ->removeImage(
                        $product,
                        $file
                    );
            }
        }
    }

    private function assignRoles(
        Product $product,
        string $file,
        array $roles
    ): void {
        foreach ($roles as $role) {
            $product->setData(
                $role,
                $file
            );
        }
    }
}
