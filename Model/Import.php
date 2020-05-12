<?php
namespace DenTikhonov\CliImportCommand\Model;

use Magento\ImportExport\Model\Import as MagentoImport;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Class Import
 * @package DenTikhonov\CliImportCommand\Model
 */
class Import
{

    const ENTITY_CATALOG_PRODUCT = 'catalog_product';
    const ENTITY_STOCK_SOURCES = 'stock_sources';

    /**
     * @var \Magento\ImportExport\Model\Import
     */
    private $importModel;

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    private $readFactory;

    /**
     * @var \Magento\ImportExport\Model\Import\Source\CsvFactory
     */
    private $csvSourceFactory;

    /**
     * @var \Magento\Indexer\Model\Indexer\CollectionFactory
     */
    private $indexerCollectionFactory;

    /**
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\ImportExport\Model\Import $importModel
     * @param \Magento\ImportExport\Model\Import\Source\CsvFactory $csvSourceFactory
     * @param \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory
     * @param \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory
     */
    public function __construct(
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\ImportExport\Model\Import $importModel,
        \Magento\ImportExport\Model\Import\Source\CsvFactory $csvSourceFactory,
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory,
        \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory
    ) {
        $this->eavConfig = $eavConfig;
        $this->csvSourceFactory = $csvSourceFactory;
        $this->indexerCollectionFactory = $indexerCollectionFactory;
        $this->readFactory = $readFactory;
        $importModel->setData(
            [
                'entity' => self::ENTITY_STOCK_SOURCES,
                'behavior' => MagentoImport::BEHAVIOR_APPEND,
                MagentoImport::FIELD_NAME_IMG_FILE_DIR => 'pub/media/catalog/product',
                MagentoImport::FIELD_NAME_VALIDATION_STRATEGY => ProcessingErrorAggregatorInterface::VALIDATION_STRATEGY_SKIP_ERRORS
            ]
        );
        $this->importModel = $importModel;
    }

    /**
     * @param $filePath Absolute file path to CSV file
     */
    public function setFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException();
        }
        $pathInfo = pathinfo($filePath);
        $validate = $this->importModel->validateSource($this->csvSourceFactory->create(
            [
                'file' => $pathInfo['basename'],
                'directory' => $this->readFactory->create($pathInfo['dirname'])
            ]
        ));
        if (!$validate) {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * @param $imagesPath
     */
    public function setImagesPath($imagesPath)
    {
        $this->importModel->setData(MagentoImport::FIELD_NAME_IMG_FILE_DIR, $imagesPath);
    }

    /**
     * @param $behavior
     */
    public function setBehavior($behavior)
    {
        if (in_array($behavior, array(
            MagentoImport::BEHAVIOR_APPEND,
            MagentoImport::BEHAVIOR_ADD_UPDATE,
            MagentoImport::BEHAVIOR_REPLACE,
            MagentoImport::BEHAVIOR_DELETE
        ))) {
            $this->importModel->setData('behavior', $behavior);
        }
    }

    public function setEntity($entity)
    {
        if (in_array($entity, array(
            self::ENTITY_STOCK_SOURCES,
            self::ENTITY_CATALOG_PRODUCT
        ))) {
            $this->importModel->setData('entity', $entity);
        }
    }

    /**
     * @return bool
     */
    public function execute()
    {
        $result = $this->importModel->importSource();
        if ($result) {
            $this->importModel->invalidateIndex();
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getFormattedLogTrace()
    {
        // Yep, there is a typo here, see https://github.com/magento/magento2/pull/2771
        return $this->importModel->getFormatedLogTrace();
    }

    /**
     * @return MagentoImport\ErrorProcessing\ProcessingError[]
     */
    public function getErrors()
    {
        return $this->importModel->getErrorAggregator()->getAllErrors();
    }
}

