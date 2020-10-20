<?php

namespace TemplateProvider\EMailTemplatesImportExport\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Module\Dir;
use Magento\Framework\File\UploaderFactory;
use TemplateProvider\EMailTemplatesImportExport\Api\ContentInterface;
use TemplateProvider\EMailTemplatesImportExport\Model\Filesystem;
use Magento\Store\Api\StoreRepositoryInterface;

class Import extends Command
{
    /**
     * @var DirectoryList
     */
    protected DirectoryList $directoryList;

    /**
     * @var Dir
     */
    protected Dir $moduleDir;

    /**
     * @var ContentInterface
     */
    protected ContentInterface $contentInterface;

    /**
     * @var Filesystem
     */
    protected Filesystem $filesystem;

    /**
     * @var StoreRepositoryInterface
     */
    protected StoreRepositoryInterface $storeRepositoryInterface;

    public function __construct(DirectoryList $directoryList, Dir $module_dir, UploaderFactory $uploaderFactory, ContentInterface $contentInterface, Filesystem $filesystem, StoreRepositoryInterface $storeRepositoryInterface)
    {
        parent::__construct();
        $this->directoryList = $directoryList;
        $this->moduleDir = $module_dir;
        $this->contentInterface = $contentInterface;
        $this->filesystem = $filesystem;
        $this->storeRepositoryInterface = $storeRepositoryInterface;
        $this->directoryList = $directoryList;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileName = $this->directoryList->getPath('var') . DIRECTORY_SEPARATOR . 'export' . DIRECTORY_SEPARATOR . $input->getArgument('file-name');
        if (file_exists($fileName)):
            $output->writeln('Import Process started...!');
            $stores = $this->storeRepositoryInterface->getList();
            $mode = $input->getArgument('mode');
            $mediaMode = $input->getArgument('media-mode');
            $storesMap = array();
            foreach ($stores as $storeInterface) :
                $storesMap[$storeInterface->getCode()] = $storeInterface->getCode();
            endforeach;
            $this->contentInterface->setMode($mode)->setMediaMode($mediaMode);
            $count = $this->contentInterface->importFromZipFile($fileName);
            $output->writeln(__('A total of %1 item(s) have been imported/updated.', $count));
        else:
            $output->writeln("{$fileName} file does not exist");
        endif;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("tp:import:email-templates");
        $this->setDescription("Sync E-Mail Templates");
        $this->addArgument('mode', InputArgument::REQUIRED, 'Which mode so you want? (skip or update)');
        $this->addArgument('media-mode', InputArgument::REQUIRED, 'Media import mode? (skip or update)');
        $this->addArgument('file-name',  InputArgument::REQUIRED, "File Name");
        parent::configure();
    }
}
