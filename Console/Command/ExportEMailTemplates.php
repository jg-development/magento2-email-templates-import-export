<?php

namespace TemplateProvider\EMailTemplatesImportExport\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Email\Model\ResourceModel\Template\CollectionFactory;
use Magento\Framework\Module\Dir;
use TemplateProvider\EMailTemplatesImportExport\Api\ContentInterface as ImportExportContentInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\Filesystem\DirectoryList;

class ExportEMailTemplates extends Command
{
    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $collectionFactory;

    /**
     * @var Dir
     */
    protected Dir $moduleReader;

    /**
     * @var DateTime
     */
    protected DateTime $dateTime;

    /**
     * @var DirectoryList
     */
    protected DirectoryList $directoryList;

    /**
     * @var ImportExportContentInterface
     */
    private ImportExportContentInterface $importExportContentInterface;

    public function __construct(Dir $moduleReader, CollectionFactory $collectionFactory, DateTime $dateTime, ImportExportContentInterface $importExportContentInterface, DirectoryList $directoryList)
    {
        $this->moduleReader = $moduleReader;
        $this->collectionFactory = $collectionFactory;
        $this->dateTime = $dateTime;
        $this->importExportContentInterface = $importExportContentInterface;
        $this->directoryList = $directoryList;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $collection = $this->collectionFactory->create();
        $eMailTemplates = [];
        $output->writeln("Export Process starting....");
        foreach ($collection as $eMailTemplate) {
            $output->write("....");
            $eMailTemplates[] = $eMailTemplate;
            $output->write("....");
        }
        $output->writeln("");
        try {
            if (!empty($eMailTemplates)):
                $fileName = $this->importExportContentInterface->asZipFile($eMailTemplates);
                $showFileName = $this->directoryList->getPath('var') . '/' . $fileName;
                if (!empty($fileName)):
                    $output->writeln("Pages successfully export at {$showFileName}");
                else:
                    $output->writeln("Error while export eMailTemplates.....");
                endif;
            else:
                $output->writeln("Data not found...!");
            endif;
        } catch (Exception $e) {
            $output->writeln("Error while export eMailTemplates.....");
            $output->writeln($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("tp:export:email-templates");
        $this->setDescription("Export eMail Templates Page");
        parent::configure();
    }
}
