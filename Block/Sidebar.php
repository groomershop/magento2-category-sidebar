<?php namespace Sebwite\Sidebar\Block;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Framework\View\Element\Template;

function endsWith($haystack, $needle) {
    return substr_compare($haystack, $needle, -strlen($needle)) === 0;
}

/**
 * Class:Sidebar
 * Sebwite\Sidebar\Block
 *
 * @author      Sebwite
 * @package     Sebwite\Sidebar
 * @copyright   Copyright (c) 2015, Sebwite. All rights reserved
 */
class Sidebar extends Template
{

    /** * @var \Magento\Catalog\Helper\Category */
    protected $_categoryHelper;

    /** * @var \Magento\Framework\Registry */
    protected $_coreRegistry;

    /** * @var \Magento\Catalog\Model\Indexer\Category\Flat\State */
    protected $categoryFlatConfig;

    /** * @var \Magento\Catalog\Model\CategoryFactory */
    protected $_categoryFactory;

    /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection */
    protected $_productCollectionFactory;

    /** @var \Magento\Catalog\Helper\Output */
    private $helper;

	/** @var \Sebwite\Sidebar\Helper\Data */
    private $_dataHelper;

    /**
     * @param Template\Context                                        $context
     * @param \Magento\Catalog\Helper\Category                        $categoryHelper
     * @param \Magento\Framework\Registry                             $registry
     * @param \Magento\Catalog\Model\Indexer\Category\Flat\State      $categoryFlatState
     * @param \Magento\Catalog\Model\CategoryFactory                  $categoryFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollectionFactory
     * @param \Magento\Catalog\Helper\Output                          $helper
     * @param array                                                   $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Catalog\Helper\Category $categoryHelper,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryFlatState,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollectionFactory,
        \Magento\Catalog\Helper\Output $helper,
		\Sebwite\Sidebar\Helper\Data $dataHelper,
        $data = [ ]
    )
    {
        $this->_categoryHelper           = $categoryHelper;
        $this->_coreRegistry             = $registry;
        $this->categoryFlatConfig        = $categoryFlatState;
        $this->_categoryFactory          = $categoryFactory;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->helper                    = $helper;
		$this->_dataHelper = $dataHelper;

        parent::__construct($context, $data);
    }

    /**
     * Get all categories
     *
     * @param bool $sorted
     * @param bool $asCollection
     * @param bool $toLoad
     *
     * @return array|\Magento\Catalog\Model\ResourceModel\Category\Collection|\Magento\Framework\Data\Tree\Node\Collection
     */
    public function getCategories($sorted = false, $asCollection = false, $toLoad = true)
    {
        $cacheKey = sprintf('%d-%d-%d-%d', $this->getSelectedRootCategory(), $sorted, $asCollection, $toLoad);
        if ( isset($this->_storeCategories[ $cacheKey ]) )
        {
            return $this->_storeCategories[ $cacheKey ];
        }

        /**
         * Check if parent node of the store still exists
         */
        $category = $this->_categoryFactory->create();

		$categoryDepthLevel = $this->_dataHelper->getCategoryDepthLevel();

        $storeCategories = $category->getCategories($this->getSelectedRootCategory(), $recursionLevel = $categoryDepthLevel, $sorted, $asCollection, $toLoad);

        $this->_storeCategories[ $cacheKey ] = $storeCategories;

        return $storeCategories;
    }

    /**
     * getSelectedRootCategory method
     *
     * @return int|mixed
     */
    public function getSelectedRootCategory()
    {
        $categoryMode = $this->_scopeConfig->getValue(
            'sebwite_sidebar/general/category'
        );

		if ( $categoryMode == 'current_category_children'){
			$currentCategory = $this->_coreRegistry->registry('current_category');
			if($currentCategory){
				return $currentCategory->getId();
			}
			return 1;
		}

		if ( $categoryMode == 'current_category_parent_children'){
			$currentCategory = $this->_coreRegistry->registry('current_category');
			if($currentCategory){
				$currentCategoryPath = $currentCategory->getPath();
				$currentCategoryPathArray = explode("/", $currentCategoryPath);
				if(isset($currentCategoryPath)){
					return $currentCategoryPathArray[2];
				}
			}
			return 1;
		}

        if ( $categoryMode === null )
        {
            return 1;
        }

        return $categoryMode;
    }

    /**
     * Retrieve subcategories
	 *
     * @param $category
     *
     * @return array
     */
    public function getSubcategories($category)
    {
        if ( $this->categoryFlatConfig->isFlatEnabled() && $category->getUseFlatResource() )
        {
            return (array)$category->getChildrenNodes();
        }

        return $category->getChildren();
    }

    public function isCurrentCategoryOrParentOfCurrentCategory($category)
    {
        $activeCategory = $this->_coreRegistry->registry('current_category');
        $activeProduct  = $this->_coreRegistry->registry('current_product');

        if ( !$activeCategory )
        {

            // Check if we're on a product page
            if ( $activeProduct !== null )
            {
                return in_array($category->getId(), $activeProduct->getCategoryIds());
            }

            return false;
        }

        // Check if this is the active category
        if ( $this->categoryFlatConfig->isFlatEnabled() && $category->getUseFlatResource() AND
            $category->getId() == $activeCategory->getId()
        )
        {
            return true;
        }

        // Check if a subcategory of this category is active
        $childrenIds = $category->getAllChildren(true);
        if ( !is_null($childrenIds) AND in_array($activeCategory->getId(), $childrenIds) )
        {
            return true;
        }

        // Fallback - If Flat categories is not enabled the active category does not give an id
        return (($category->getName() == $activeCategory->getName()) ? true : false);
    }

    public function isCurrentCategory($category)
    {
        $currentCategory = $this->_coreRegistry->registry('current_category');
        if (!$currentCategory) {
            return false;
        }

        if ($currentCategory->getId() != $category->getId()) {
            return false;
        }

        $categoryPathSuffix = join(
            array_reverse(
                array_map(
                    function($c) {
                        return $c->getId();
                    },
                    $category->getPath()
                )
            ),
            "/"
        );
        return endsWith($currentCategory->getPath(), $categoryPathSuffix);
    }

    /**
     * @deprecated
     */
    public function isActive($category)
    {
        return $this->isCurrentCategoryOrParentOfCurrentCategory($category);
    }

    /**
     * Return Category Id for $category object
     *
     * @param $category
     *
     * @return string
     */
    public function getCategoryUrl($category)
    {
        return $this->_categoryHelper->getCategoryUrl($category);
    }

    /**
     * Return Is Enabled config option
     *
     * @return string
     */
    public function isEnabled()
    {
        return $this->_dataHelper->isEnabled();
    }

    /**
     * Return Title Text for menu
     *
     * @return string
     */
    public function getTitleText()
    {
        return $this->_dataHelper->getTitleText();
    }

    /**
     * Return Menu Open config option
     *
     * @return string
     */
    public function isOpenOnLoad()
    {
        return $this->_dataHelper->isOpenOnLoad();
    }
}