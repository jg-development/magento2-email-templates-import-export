<?php

namespace TemplateProvider\EMailTemplatesImportExport\Model;

use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Framework\Json\DecoderInterface;
use Magento\Framework\Json\EncoderInterface;
use Magento\Email\Model\ResourceModel\Template\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use TemplateProvider\EMailTemplatesImportExport\Api\ContentInterface;
use Magento\Framework\Filesystem\Io\File;

class Content implements ContentInterface
{
    const JSON_FILENAME = 'emailtemplates.json';
    const MEDIA_ARCHIVE_PATH = 'media';

    /**
     * @var StoreRepositoryInterface
     */
    protected StoreRepositoryInterface $storeRepositoryInterface;

    /**
     * @var EncoderInterface
     */
    protected EncoderInterface $encoderInterface;

    /**
     * @var DecoderInterface
     */
    protected DecoderInterface $decoderInterface;

    /**
     * @var Filesystem
     */
    protected Filesystem $filesystem;

    /**
     * @var File
     */
    protected File $file;

    /**
     * @var DateTime
     */
    protected DateTime $dateTime;

    /**
     * @var string
     */
    protected string $mode;

    /**
     * @var string
     */
    protected string $mediaMode;

    /**
     * @var array
     */
    protected array $storesMap;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    public function __construct(
        StoreRepositoryInterface $storeRepositoryInterface,
        EncoderInterface $encoderInterface,
        DecoderInterface $decoderInterface,
        CollectionFactory $collectionFactory,
        Filesystem $filesystem,
        File $file,
        DateTime $dateTime
    )
    {
        $this->storeRepositoryInterface = $storeRepositoryInterface;
        $this->encoderInterface = $encoderInterface;
        $this->decoderInterface = $decoderInterface;
        $this->collectionFactory = $collectionFactory;
        $this->filesystem = $filesystem;
        $this->file = $file;
        $this->dateTime = $dateTime;

        $this->mode = ContentInterface::EMAIL_TEMPLATES_MODE_UPDATE;
        $this->mediaMode = ContentInterface::MEDIA_MODE_UPDATE;

        $this->storesMap = [];
        $stores = $this->storeRepositoryInterface->getList();
        foreach ($stores as $store) {
            $this->storesMap[$store->getCode()] = $store->getCode();
        }
    }

    /**
     * Create a zip file and return its name
     * @param \Magento\Email\Model\Template[] $eMailTemplates
     * @return string
     */
    public function asZipFile(array $eMailTemplates): string
    {
        $contentArray = $this->eMailTemplatesToArray($eMailTemplates);

        $jsonPayload = $this->encoderInterface->encode($contentArray);

        $exportPath = $this->filesystem->getExportPath();

        $zipFile = $exportPath . '/' . sprintf('emailtemplates_%s.zip', $this->dateTime->date('Ymd_His'));
        $relativeZipFile = Filesystem::EXPORT_PATH . '/' . sprintf('emailtemplates_%s.zip', $this->dateTime->date('Ymd_His'));

        $zipArchive = new \ZipArchive();
        $zipArchive->open($zipFile, \ZipArchive::CREATE);

        $zipArchive->addFromString(self::JSON_FILENAME, $jsonPayload);

        foreach ($contentArray['media'] as $mediaFile) {
            $absMediaPath = $this->filesystem->getMediaPath($mediaFile);
            if ($this->file->fileExists($absMediaPath, true)) {
                $zipArchive->addFile($absMediaPath, self::MEDIA_ARCHIVE_PATH . '/' . $mediaFile);
            }
        }

        $zipArchive->close();

        return $relativeZipFile;
    }

    /**
     * Return EMailTemplates pages as array
     * @param \Magento\Email\Model\Template[] $eMailTemplates
     * @return array
     */
    private function eMailTemplatesToArray(array $eMailTemplates): array
    {
        $eMailTemplatesInformation = [];
        $media = [];

        foreach ($eMailTemplates as $eMailTemplate) {
            $eMailTemplateContent = $this->eMailTemplateToArray($eMailTemplate);
            $eMailTemplatesInformation[$eMailTemplate->getId()] = $eMailTemplateContent;
            $media = array_merge($media, $eMailTemplateContent['media']);
        }

        return [
            'eMailTemplates' => $eMailTemplatesInformation,
            'media' => $media,
        ];
    }

    /**
     * Return EMailTemplates page to array
     * @param \Magento\Email\Model\Template $emailTemplate
     * @return array
     */
    private function eMailTemplateToArray(\Magento\Email\Model\Template $emailTemplate): array
    {
        $media = $this->getMediaAttachments($emailTemplate->getContent());

        $payload = [
            'emailtemplate' => [
                'id' => $emailTemplate->getId(),
                'template_code' => $emailTemplate->getTemplateCode(),
                'template_text' => $emailTemplate->getTemplateText(),
                'template_styles' => $emailTemplate->getTemplateStyles(),
                'template_type' => $emailTemplate->getTemplateType(),
                'template_subject' => $emailTemplate->getTemplateSubject(),
                'template_sender_name' => $emailTemplate->getTemplateSenderName(),
                'template_sender_email' => $emailTemplate->getTemplateSenderEmail(),
                'added_at' => $emailTemplate->getAddedAt(),
                'modified_at' => $emailTemplate->getModifiedAt(),
                'orig_template_code' => $emailTemplate->getOrigTemplateCode(),
                'orig_template_variables' => $emailTemplate->getOrigTemplateVariables(),
                'is_legacy' => $emailTemplate->getIsLegacy(),
            ],
            'media' => $media,
        ];

        return $payload;
    }

    /**
     * Get media attachments from content
     * @param $content
     * @return array
     */
    private function getMediaAttachments($content): array
    {
        if (preg_match_all('/\{\{media.+?url\s*=\s*("|&quot;)(.+?)("|&quot;).*?\}\}/', $content, $matches)) {
            return $matches[2];
        }

        return [];
    }

    /**
     * Import contents from zip archive and return number of imported records (-1 on error)
     * @param string $fileName
     * @return int
     * @throws \Exception
     */
    public function importFromZipFile($fileName): int
    {
        $zipArchive = new \ZipArchive();
        $res = $zipArchive->open($fileName);
        if ($res !== true) {
            throw new \Exception('Cannot open ZIP archive');
        }

        $subPath = md5(date(DATE_RFC2822));
        $extractPath = $this->filesystem->getExtractPath($subPath);

        $zipArchive->extractTo($extractPath);
        $zipArchive->close();

        $pagesFile = $extractPath . '/' . self::JSON_FILENAME;
        if (!$this->file->fileExists($pagesFile, true)) {
            throw new \Exception(self::JSON_FILENAME . ' is missing');
        }

        $jsonString = $this->file->read($pagesFile);
        $eMailTemplatesData = $this->decoderInterface->decode($jsonString);

        $count = $this->importFromArray($eMailTemplatesData, $extractPath);

        $this->file->rmdir($extractPath, true);

        return $count;
    }

    /**
     * Import contents from array and return number of imported records (-1 on error)
     * @param array $payload
     * @param string|null $archivePath = null
     * @return int
     * @throws \Exception
     */
    private function importFromArray(array $payload, string $archivePath = null): int
    {
        if (!isset($payload['eMailTemplates'])) {
            throw new \Exception('Invalid json archive');
        }

        $count = 0;

        foreach ($payload['eMailTemplates'] as $key => $eMailTemplate) {
            if ($this->importEMailTemplatesFromArray($eMailTemplate)) {
                $count++;
            }
        }

        if ($archivePath && ($count > 0) && ($this->mediaMode != ContentInterface::MEDIA_MODE_NONE)) {
            foreach ($payload['media'] as $mediaFile) {
                $sourceFile = $archivePath . '/' . self::MEDIA_ARCHIVE_PATH . '/' . $mediaFile;
                $destFile = $this->filesystem->getMediaPath($mediaFile);

                if ($this->file->fileExists($sourceFile, true)) {
                    if ($this->file->fileExists($destFile, true) &&
                        ($this->mediaMode == ContentInterface::MEDIA_MODE_SKIP)
                    ) {
                        continue;
                    }

                    if (!$this->file->mkdir(dirname($destFile), 0777) ||
                        !$this->file->cp($sourceFile, $destFile)) {
                        throw new \Exception('Unable to save image: ' . $mediaFile);
                    }
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Set mode
     * @param $mode
     * @return ContentInterface
     */
    public function setMode($mode): ContentInterface
    {
        $this->mode = $mode;
        return $this;
    }

    /**
     * Set media mode
     * @param $mode
     * @return ContentInterface
     */
    public function setMediaMode($mode): ContentInterface
    {
        $this->mediaMode = $mode;
        return $this;
    }

    /**
     * Import a single e-mail template from an array and return false on error and true on success
     * @param array[] $eMailTemplates
     * @return bool
     */
    private function importEMailTemplatesFromArray(array $eMailTemplateSource): bool
    {
        /** @var \Magento\Email\Model\Template $eMailTemplate */
        $eMailTemplate = $this->collectionFactory->create()->getItemById($eMailTemplateSource['emailtemplate']['id']);

        if ($this->mode == ContentInterface::EMAIL_TEMPLATES_MODE_SKIP) {
            return false;
        }

        $eMailTemplateContent = $eMailTemplateSource['emailtemplate'];

        $eMailTemplate
            ->setId($eMailTemplateContent['id'])
            ->setTemplateCode($eMailTemplateContent['template_code'])
            ->setTemplateText($eMailTemplateContent['template_text'])
            ->setTemplateStyles($eMailTemplateContent['template_styles'])
            ->setTemplateType($eMailTemplateContent['template_type'])
            ->setTemplateSubject($eMailTemplateContent['template_subject'])
            ->setTemplateSenderName($eMailTemplateContent['template_sender_name'])
            ->setTemplateSenderEmail($eMailTemplateContent['template_sender_email'])
            ->setAddedAt($eMailTemplateContent['added_at'])
            ->setModifiedAt($eMailTemplateContent['modified_at'])
            ->setOrigTemplateCode($eMailTemplateContent['orig_template_code'])
            ->setOrigTemplateVariables($eMailTemplateContent['orig_template_variables'])
            ->setIsLegacy($eMailTemplateContent['is_legacy']);

        $eMailTemplate->save();

        return true;
    }
}
