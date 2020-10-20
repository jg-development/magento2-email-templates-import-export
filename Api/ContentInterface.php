<?php

namespace TemplateProvider\EMailTemplatesImportExport\Api;
/**
 * Interface ContentInterface
 * @package TemplateProvider\EMailTemplatesImportExport\Api
 * @api
 */
interface ContentInterface
{
    const EMAIL_TEMPLATES_MODE_UPDATE = 'update';
    const EMAIL_TEMPLATES_MODE_SKIP = 'skip';

    const MEDIA_MODE_NONE = 'none';
    const MEDIA_MODE_UPDATE = 'update';
    const MEDIA_MODE_SKIP = 'skip';

    /**
     * Create a zip file and return its name
     * @param \Magento\Email\Model\Template[] $eMailTemplates
     * @return string
     */
    public function asZipFile(array $eMailTemplates): string;

    /**
     * Import contents from zip archive and return number of imported records (-1 on error)
     * @param string $fileName
     * @return int
     */
    public function importFromZipFile($fileName): int;

    /**
     * Set mode on import
     * @param $mode
     * @return ContentInterface
     */
    public function setMode($mode): ContentInterface;

    /**
     * Set media mode on import
     * @param $mode
     * @return ContentInterface
     */
    public function setMediaMode($mode): ContentInterface;
}
