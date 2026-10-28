<?php
namespace exface\JEasyUIFacade\Facades\Elements;

use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryDataConfiguratorTrait;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Widgets\iSupportWidgetSetups;
use exface\Core\Widgets\ButtonGroup;
use exface\Core\Widgets\Data;
use exface\Core\Widgets\DataTable;
use exface\Core\Widgets\DataTableConfigurator;
use exface\Core\Widgets\Filter;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * Renders the header of a data widget and a tabbed settings dialog for the rest of the configurator.
 * 
 * See [architecture documentation](../../../Docs/Developer_docs/DataConfigurator.md) for technical details.
 * 
 * @method DataTableConfigurator getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class EuiDataConfigurator extends EuiTabs
{    
    use JqueryDataConfiguratorTrait {
        buildJsDataGetter as buildJsDataGetterViaTrait;
        buildJsResetter as buildJsResetterViaTrait;
    }
    
    private $btnCollaps = null;
    
    private $headerPanelId = null;
    
    private $searchableFields = null;
    
    private $sortableAttributes = null;
    
    public function buildHtml()
    {
        $html = parent::buildHtml();
        foreach ($this->getWidget()->getTabs() as $tab) {
            if ($tab->countWidgetsVisible() === 0) {
                $tab->setHidden(true);
            }
        }
        if ($this->getWidget()->countWidgetsVisible() === 0) {
            return '<div style="display: none">' . $html . '</div>';
        }
        return $html;
    }
    
    public function getIdOfHeaderPanel() : string
    {
        if (is_null($this->headerPanelId)) {
            $this->headerPanelId = $this->getId() . '_toolbar';
        }
        return $this->headerPanelId;
    }

    /**
     *
     * @param string $toolbarId
     * @return string
     */
    protected function getIdOfHeaderFooter(string $toolbarId) : string
    {
        return $toolbarId . '_footer';
    }
    
    /**
     * Creates the HTML for the header controls: filters, sorters, buttons, etc.
     * @return string
     */
    public function buildHtmlHeaderPanel(string $toolbarId, string $toolbarHtml = '')
    {
        $configuredWidget = $this->getWidget()->getWidgetConfigured();
        $footerId = $this->getIdOfHeaderFooter($toolbarId);
        $header_style = '';
        $toolbar_style = '';
        $panel_options = "border: false";
        
        // Prepare the header with the configurator and the toolbars
        $configuratorWidget = $this->getWidget();
        
        // jEasyUI will not resize the configurator once the datagrid is resized
        // (don't know why), so we need to do it manually. The `setTimeout()` is
        // also important as without it the flexible width of the filters does not
        // work and they are rendered too small. The `doLayout` on the other hand
        // makes sure, that complex filter widgets (like RangeFilter) are rendered
        // correctly in panels, that are not visible right away - e.g. in secondary
        // tabs like in the default editor of `axenox.Deployer.project` in the tab
        // "Deployments".
        $this->getFacade()->getElement($configuredWidget)->addOnResizeScript("setTimeout(function(){
            $('#{$toolbarId}').find('.easyui-panel').panel('doLayout');
            {$this->getFacade()->getElement($configuratorWidget->getFilterTab())->buildJsLayouter()}
        },0);");

        if ($configuredWidget->getHideHeader() || $configuratorWidget->isCollapsed() === true) {
            $panel_options .= ', collapsed: true';
        }

        // Hiding the entire header also hides the toolbar with the buttons - including the button
        // to open the configurator dialog. Thus, it may only be hidden if the toolbar is not needed.
        if ($this->hasHeaderToolbar() === false) {
            $header_style = 'visibility: hidden; height: 0px; padding: 0px;';
            $toolbar_style = 'display: none; height: 0;';
        }
        
        // Keep all-hidden filters in the collapsed header so their inputs remain available to the data getter.
        $filtersHtml = $configuredWidget->getHideHeader() === true && $this->hasTabFilters()
            ? ''
            : $this->getFacade()->getElement($configuratorWidget->getFilterTab())->buildHtml();
        
        return <<<HTML
        
        <div id="{$toolbarId}" style="{$header_style}">
            <div class="easyui-panel exf-data-header" data-options="footer: '#{$footerId}', {$panel_options}">
                {$filtersHtml}
            </div>
            <div id="{$footerId}" class="datatable-toolbar" style="{$toolbar_style}">
                {$toolbarHtml}
            </div>
        </div>
        {$this->buildHtmlConfiguratorDialog()}
                
HTML;
    }

    public function buildHtmlHeaderPanelLinked($toolbarId, string $toolbarHtml = '')
    {
        $footerId = $this->getIdOfHeaderFooter($toolbarId);
        $header_style = '';
        $toolbar_style = '';
        $panel_options = "border: false";

        return <<<HTML
        
        <div id="{$toolbarId}" style="{$header_style}">
            <div class="easyui-panel exf-data-header" data-options="footer: '#{$footerId}', {$panel_options}">
                
            </div>
            <div id="{$footerId}" class="datatable-toolbar" style="{$toolbar_style}">
                {$toolbarHtml}
            </div>
        </div>
                
HTML;
    }
    
    /**
     * Returns the default number of columns to layout this widget.
     *
     * @return integer
     */
    public function getNumberOfColumnsByDefault() : int
    {
        return $this->getFacade()->getConfig()->getOption("WIDGET.DATACONFIGURATOR.COLUMNS_BY_DEFAULT");
    }

    /**
     * {@inheritDoc}
     * @see EuiTabs::buildJs()
     */
    public function buildJs()
    {
        return parent::buildJs() . <<<JS

{$this->buildJsRegisterOnActionPerformed($this->buildJsRefreshConfiguredWidget(true))}
JS;
    }

    /**
     * Returns JS code required for the header of the panel-wrapper for the widget
     *
     * The panel header does not show the entire configurator, but rather just the contents of the filters
     * tab with some additional logic like refresh on Enter-key. So we do not need all the JS here. In
     * particular, no JS for sorters or widget setups.
     *
     * @return string
     */
    public function buildJsForPanelHeader()
    {
        return <<<JS

{$this->getFacade()->getElement($this->getWidget()->getFilterTab())->buildJs()}
{$this->buildJsRefreshOnEnter()}
{$this->buildJsRegisterOnActionPerformed($this->buildJsRefreshConfiguredWidget(true))}
{$this->buildJsConfiguratorDialog()}
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiTabs::buildJsTabsInit()
     */
    protected function buildJsTabsInit() : string
    {
        // TODO initialize tabs here (or remove the method) once there really is a rendered configurator control
        return '';
    }
    
    public function addButtonToCollapseExpand(ButtonGroup $buttonGroup, int $position = 0, string $onFinishedJs = '')
    {
        $tableEl = $this->getFacade()->getElement($this->getWidget()->getWidgetConfigured());
        $collapseButton = WidgetFactory::createFromUxon($this->getWidget()->getPage(), new UxonObject([
            'widget_type' => 'Button',
            'id' => $this->getIdOfCollapseButton($tableEl->getId()),
            'action' => [
                'alias' => 'exface.Core.CustomFacadeScript'
            ],
            'icon' => $this->getWidget()->isCollapsed() === true ? Icons::CHEVRON_DOWN : Icons::CHEVRON_UP,
            'caption' => $this->translate('WIDGET.DATATABLE.CONFIGURATOR_EXPAND_COLLAPSE'),
            'align' => 'right',
            'hide_caption' => true
        ]), $buttonGroup);
        $buttonGroup->addButton($collapseButton, $position);
        $this->btnCollaps = $collapseButton;
        
        // Give the script to the button AFTER being created to make sure eventual id spaces are appended to the
        // id, which is used inside the script. Otherwise the id will not match in dialogs with id spaces!
        $collapseButton->getAction()->setScript(<<<JS
            
    var toggleBtn = $('#{$this->getFacade()->getElement($collapseButton)->getId()}');
    var confPanel = toggleBtn.parents('.datatable-toolbar').prev();
    if (confPanel.css('display') === 'none') {
        confPanel.panel('expand');
        toggleBtn.find('.fa-chevron-down').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    } else {
        confPanel.panel('collapse');
        toggleBtn.find('.fa-chevron-up').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    }

    {$onFinishedJs}
    
JS);
        return $collapseButton;
    }

    /**
     * Adds a button that opens the configurator dialog - between the header filter toggle and the collapse button.
     *
     * @param ButtonGroup $buttonGroup
     * @param int $position
     * @param string $onFinishedJs
     * @return \exface\Core\Widgets\Button|NULL
     */
    public function addButtonsToSearchGroup(ButtonGroup $buttonGroup, int $position = 0, string $onFinishedJs = '')
    {
        if ($this->hasConfiguratorDialog() === false) {
            return null;
        }
        
        $tableEl = $this->getFacade()->getElement($this->getWidget()->getWidgetConfigured());
        /** @var \exface\Core\Widgets\Button $configuratorButton */
        $configuratorButton = WidgetFactory::createFromUxon($this->getWidget()->getPage(), new UxonObject([
            'widget_type' => 'Button',
            'id' => $this->getIdOfConfiguratorButton($tableEl->getId()),
            'action' => [
                'alias' => 'exface.Core.CustomFacadeScript'
            ],
            'icon' => Icons::COG,
            'caption' => $this->translate('WIDGET.DATACONFIGURATOR.OPEN'),
            'align' => 'right',
            'hide_caption' => true
        ]), $buttonGroup);
        $buttonGroup->addButton($configuratorButton, $position);
        
        $configuratorButton->getAction()->setScript(<<<JS

    {$this->buildJsConfiguratorDialogShow()}
    {$onFinishedJs}

JS);
        return $configuratorButton;
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsRefreshOnEnter()
    {
        // Use keyup() instead of keypress() because the latter did not work with jEasyUI combos.
        return <<<JS
        setTimeout(function(){
            $('#{$this->getFacade()->getElement($this->getWidget()->getFilterTab())->getId()}').find('input').keyup(function (ev) {
                var keycode = (ev.keyCode ? ev.keyCode : ev.which);
                if (keycode == '13') {
                    {$this->buildJsRefreshConfiguredWidget(false)};
                }
            })
        }, 10);
        
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildHtmlHeadTags()
     */
    public function buildHtmlHeadTags()
    {
        $includes = parent::buildHtmlHeadTags();
        $facade = $this->getFacade();
        if ($this->hasTabAdvancedSearch()) {
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.JEASYUI.EXTENSIONS.CONDITIONBUILDER') . '"></script>';
        }
        if ($this->hasTabSorters()) {
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.JEASYUI.EXTENSIONS.SORTERBUILDER') . '"></script>';
        }
        return $includes;
    }
    
    /**
     * Returns TRUE if the top toolbar of the configured widget is to be shown.
     * 
     * By default the toolbar is only hidden if the header AND the caption are hidden - just like in
     * other facades. This can be overridden via `hide_header_toolbar` in the model of the data widget.
     * 
     * @return bool
     */
    protected function hasHeaderToolbar() : bool
    {
        $dataWidget = $this->getWidget()->getWidgetConfigured();
        if (! ($dataWidget instanceof Data)) {
            return true;
        }
        if (null !== $hideToolbar = $dataWidget->getHideHeaderToolbar()) {
            return ! $hideToolbar;
        }
        return $dataWidget->getHideHeader() !== true;
    }
    
    /**
     * The filters are only part of the dialog if the header with the quick filters is hidden permanently
     * 
     * @return bool
     */
    protected function hasTabFilters() : bool
    {
        return $this->getWidget()->getWidgetConfigured()->getHideHeader() !== false
            && $this->getWidget()->getFilterTab()->countWidgetsVisible() > 0;
    }

    /**
     * Returns TRUE if the unset hide_header option is to be resolved in the browser.
     *
     * @return bool
     */
    protected function hasResponsiveHeader() : bool
    {
        return $this->getWidget()->getWidgetConfigured()->getHideHeader() === null;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasTabSorters() : bool
    {
        if ($this->getWidget()->isDisabled() === true) {
            return false;
        }
        return empty($this->getSortableAttributes()) === false;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasTabAdvancedSearch() : bool
    {
        if ($this->getWidget()->isDisabled() === true) {
            return false;
        }
        return empty($this->getSearchableAttributes()) === false;
    }

    /**
     * Returns TRUE if the configured DataTable supports persisted setups.
     *
     * @return bool
     */
    protected function hasTabSetups() : bool
    {
        return $this->getWidget() instanceof iSupportWidgetSetups
            && $this->getWidget()->hasSetups()
            && $this->getFacade()->getElement($this->getWidget()->getWidgetConfigured()) instanceof EuiDataTable;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasConfiguratorDialog() : bool
    {
        return $this->hasTabFilters() || $this->hasTabSorters() || $this->hasTabAdvancedSearch() || $this->hasTabSetups();
    }
    
    /**
     * 
     * @return string
     */
    public function getIdOfConfiguratorDialog() : string
    {
        return $this->getId() . '_dialog';
    }
    
    /**
     * 
     * @return string
     */
    protected function getIdOfConfiguratorTabs() : string
    {
        return $this->getId() . '_dialog_tabs';
    }

    /**
     *
     * @return string
     */
    protected function getIdOfFiltersTab() : string
    {
        return $this->getIdOfConfiguratorTabs() . '_filters';
    }

    /**
     * Returns the HTML ID of the sorting tab.
     *
     * @return string
     */
    protected function getIdOfSortersTab() : string
    {
        return $this->getIdOfConfiguratorTabs() . '_sorters';
    }

    /**
     * Returns the HTML ID of the advanced search tab.
     *
     * @return string
     */
    protected function getIdOfAdvancedSearchTab() : string
    {
        return $this->getIdOfConfiguratorTabs() . '_advanced_search';
    }

    /**
     *
     * @return string
     */
    protected function getIdOfResponsiveStaging() : string
    {
        return $this->getIdOfConfiguratorTabs() . '_responsive_staging';
    }

    /**
     *
     * @param string $tableId
     * @return string
     */
    protected function getIdOfCollapseButton(string $tableId) : string
    {
        return 'headerCollapseButton_' . $tableId;
    }

    /**
     *
     * @param string $tableId
     * @return string
     */
    protected function getIdOfConfiguratorButton(string $tableId) : string
    {
        return 'configuratorButton_' . $tableId;
    }
    
    /**
     * 
     * @return string
     */
    public function getIdOfSorterBuilder() : string
    {
        return $this->getId() . '_sorters';
    }
    
    /**
     * 
     * @return string
     */
    public function getIdOfConditionBuilder() : string
    {
        return $this->getId() . '_search';
    }
    
    /**
     * Renders the (initially hidden) container for the configurator dialog and its tabs
     * 
     * @return string
     */
    protected function buildHtmlConfiguratorDialog() : string
    {
        if ($this->hasConfiguratorDialog() === false) {
            return '';
        }
        
        $widget = $this->getWidget();
        $tabsHtml = '';

        if ($this->hasTabSetups()) {
            $tabsHtml .= $this->getFacade()->getElement($widget->getSetupsTab())->buildHtml();
        }
        
        if ($this->hasTabFilters()) {
            $tab = $widget->getFilterTab();
            $filtersHtml = $this->hasResponsiveHeader() ? '' : $this->getFacade()->getElement($tab)->buildHtml();
            $tabsHtml .= <<<HTML

                <div id="{$this->getIdOfFiltersTab()}" title="{$this->escapeString($tab->getCaption(), false, true)}" data-options="iconCls: '{$this->buildCssIconClass(Icons::FILTER)}'" style="padding: 10px; overflow: auto;">
                    {$filtersHtml}
                </div>
HTML;
        }
        
        if ($this->hasTabSorters()) {
            $tab = $widget->getSorterTab();
            $tabsHtml .= <<<HTML

                <div id="{$this->getIdOfSortersTab()}" title="{$this->escapeString($tab->getCaption(), false, true)}" data-options="iconCls: '{$this->buildCssIconClass(Icons::SORT)}'" style="padding: 10px; overflow: auto;">
                    <div id="{$this->getIdOfSorterBuilder()}"></div>
                </div>
HTML;
        }
        
        if ($this->hasTabAdvancedSearch()) {
            $tabsHtml .= <<<HTML

                <div id="{$this->getIdOfAdvancedSearchTab()}" title="{$this->escapeString($this->translate('WIDGET.DATACONFIGURATOR.ADVANCED_SEARCH_TAB_CAPTION'), false, true)}" data-options="iconCls: '{$this->buildCssIconClass(Icons::SEARCH)}'" style="padding: 10px; overflow: auto;">
                    <div id="{$this->getIdOfConditionBuilder()}"></div>
                </div>
HTML;
        }

        return <<<HTML

        <div id="{$this->getIdOfResponsiveStaging()}" style="display: none;"></div>
        <div id="{$this->getIdOfConfiguratorDialog()}" class="exf-data-configurator" style="display: none;">
            <div id="{$this->getIdOfConfiguratorTabs()}">
                {$tabsHtml}
            </div>
        </div>

HTML;
    }
    
    /**
     * Returns the JS to initialize the controls inside the configurator dialog and the function to open it
     * 
     * The dialog itself is created lazily - on the first click on the configurator button. The sorter and
     * condition builders on the other hand are initialized right away because their values are read on
     * every data request - no matter if the dialog was ever opened or not.
     * 
     * @return string
     */
    protected function buildJsConfiguratorDialog() : string
    {
        if ($this->hasConfiguratorDialog() === false) {
            return '';
        }
        
        $dataEl = $this->getFacade()->getElement($this->getWidget()->getWidgetConfigured());
        /** @var EuiTab|null $filterTabEl */
        $filterTabEl = $this->hasTabFilters() ? $this->getFacade()->getElement($this->getWidget()->getFilterTab()) : null;
        $layoutFiltersJs = $filterTabEl ? $filterTabEl->buildJsLayouter() : '';
        $layoutFiltersOnSelectJs = $this->hasTabFilters() ? <<<JS
, onSelect: function(title, index) {
    if ($(this).tabs('getTab', index).attr('id') === '{$this->getIdOfFiltersTab()}') {
        setTimeout(function(){
            {$layoutFiltersJs}
        }, 0);
    }
}
JS : '';
        $clearHeaderSortersJs = ($dataEl instanceof EuiData && $this->hasTabSorters()) ? $dataEl->buildJsHeaderSortersReset() : '';
        $syncHeaderFiltersJs = ($dataEl instanceof EuiData && $this->hasTabAdvancedSearch()) ? $dataEl->buildJsHeaderFiltersSet($this->buildJsSearchConditionsGetter()) : '';
        $clearHeaderFiltersJs = ($dataEl instanceof EuiData && $this->hasTabAdvancedSearch()) ? $dataEl->buildJsHeaderFiltersReset() : '';
        $responsiveHeaderJs = $this->buildJsResponsiveHeader();
        $setupsJs = '';
        $refreshSetupsJs = '';
        $clearSetupJs = '';
        $quickSelectJs = '';
        if ($this->hasTabSetups()) {
            /** @var Data $setupsTable */
            $setupsTable = $this->getWidget()->getSetupsTab()->getWidgetFirst();
            $setupsTable->setAutoloadData(false);
            $configuredWidget = $this->getWidget()->getWidgetConfigured();
            /** @var EuiDataTable $setupsTableElement */
            $setupsTableElement = $this->getFacade()->getElement($setupsTable);
            $setupsTableElement->addOnLoadSuccess('exfSetupManager.markCurrentSetupAsActive(' .
                $this->escapeString($setupsTableElement->getId()) . ', ' .
                $this->escapeString($configuredWidget->getUiScreen()->getUrlSlug()) . ', ' .
                $this->escapeString($configuredWidget->getIdInScreen()) . ', ' .
                $this->escapeString($configuredWidget->getMetaObject()->getId()) . ');');
            $setupsJs = $this->getFacade()->getElement($this->getWidget()->getSetupsTab())->buildJs();
            $refreshSetupsJs = 'exfSetupManager.quickSelect.load(' . $this->escapeString($setupsTableElement->getId()) . ');';
            $clearSetupJs = "exfSetupManager.dexie.deleteCurrentSetup(" .
                $this->escapeString($configuredWidget->getUiScreen()->getUrlSlug()) . ', ' .
                $this->escapeString($configuredWidget->getIdInScreen()) . ', ' .
                $this->escapeString($configuredWidget->getMetaObject()->getId()) . ').then(function(){' .
                'exfSetupManager.markCurrentSetupAsActive(' .
                $this->escapeString($setupsTableElement->getId()) . ', ' .
                $this->escapeString($configuredWidget->getUiScreen()->getUrlSlug()) . ', ' .
                $this->escapeString($configuredWidget->getIdInScreen()) . ', ' .
                $this->escapeString($configuredWidget->getMetaObject()->getId()) . ');' .
                '});';
            /** @var EuiButton $saveSetupButtonElement */
            $saveSetupButtonElement = $this->getFacade()->getElement($this->getWidget()->getButtonToSaveSetup());
            /** @var EuiButton $updateSetupButtonElement */
            $updateSetupButtonElement = $this->getFacade()->getElement($this->getWidget()->getButtonToUpdateSetup());
            /** @var EuiButton $editSetupButtonElement */
            $editSetupButtonElement = $this->getFacade()->getElement($this->getWidget()->getButtonToEditSetup());
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            $defaultCaption = $configuredWidget->getCaption() ?: $configuredWidget->getMetaObject()->getName();
            $quickSelectJs = <<<JS

exfSetupManager.quickSelect.register({
    tableId: {$this->escapeString($dataEl->getId())},
    setupsTableId: {$this->escapeString($setupsTableElement->getId())},
    configuratorButtonId: {$this->escapeString($this->getIdOfConfiguratorButton($dataEl->getId()))},
    captionButtonId: {$this->escapeString($dataEl->getId() . '_setupQuickSelect')},
    captionMenuId: {$this->escapeString($dataEl->getId() . '_setupQuickSelectMenu')},
    configuratorMenuId: {$this->escapeString($dataEl->getId() . '_setupConfiguratorMenu')},
    slug: {$this->escapeString($configuredWidget->getUiScreen()->getUrlSlug())},
    widgetId: {$this->escapeString($configuredWidget->getIdInScreen())},
    objectId: {$this->escapeString($configuredWidget->getMetaObject()->getId())},
    defaultCaption: {$this->escapeString($defaultCaption)},
    loadingCaption: '...',
    emptyCaption: {$this->escapeString($translator->translate('WIDGET.DATA.NO_DATA_FOUND'))},
    clearCaption: {$this->escapeString($translator->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_CLEAR'))},
    clearIconCls: '{$this->buildCssIconClass(Icons::UNDO)}',
    openCaption: {$this->escapeString($translator->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_ALL'))},
    saveCaption: {$this->escapeString($translator->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_SAVE'))},
    applyCaption: {$this->escapeString($translator->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_APPLY'))},
    updateCaption: {$this->escapeString($this->getWidget()->getButtonToUpdateSetup()->getCaption())},
    editCaption: {$this->escapeString($this->getWidget()->getButtonToEditSetup()->getCaption())},
    apply: function(row) {
        {$dataEl->buildJsCallFunction(DataTable::FUNCTION_APPLY_SETUP, ['[#SETUP_UXON#]'], '{rows: [row]}')}
    },
    update: function(row) {
        if (exfSetupManager.quickSelect.selectSetupRow({$this->escapeString($setupsTableElement->getId())}, row.UID)) {
            {$updateSetupButtonElement->buildJsClickFunctionName()}();
        }
    },
    edit: function(row) {
        if (exfSetupManager.quickSelect.selectSetupRow({$this->escapeString($setupsTableElement->getId())}, row.UID)) {
            {$editSetupButtonElement->buildJsClickFunctionName()}();
        }
    },
    clear: function() {
        {$clearHeaderSortersJs}
        {$clearHeaderFiltersJs}
        {$clearSetupJs}
        {$this->buildJsResetter()}
    },
    openConfigurator: function() {
        {$this->buildJsFunctionPrefix()}ShowConfigurator(true);
    },
    save: function() {
        {$saveSetupButtonElement->buildJsClickFunctionName()}();
    }
});
JS;
        }
        
        return <<<JS

    {$this->buildJsConfiguratorBadgeFunctions()}
{$this->buildJsSorterBuilderInit()}
{$this->buildJsConditionBuilderInit()}
    {$this->buildJsConfiguratorBadgesUpdate()}
    {$setupsJs}
    {$responsiveHeaderJs}
    {$quickSelectJs}

function {$this->buildJsFunctionPrefix()}ShowConfigurator(bShowSetups) {
    var jqDialog = $('#{$this->getIdOfConfiguratorDialog()}');
    var bMobile = window.matchMedia('(max-width: 600px)').matches;
    if (jqDialog.data('dialog') === undefined) {
        jqDialog.show().dialog({
            title: {$this->escapeString($this->translate('WIDGET.DATACONFIGURATOR.DIALOG_TITLE'))},
            width: bMobile ? $(window).width() : 800,
            height: bMobile ? $(window).height() : 500,
            closed: true,
            modal: true,
            cache: true,
            maximizable: true,
            buttons: [
                {
                    text: {$this->escapeString($this->translate('WIDGET.DATACONFIGURATOR.BUTTON_RESET'))},
                    iconCls: '{$this->buildCssIconClass(Icons::UNDO)}',
                    plain: true,
                    handler: function(){
                        $('#{$this->getIdOfConfiguratorDialog()}').dialog('close');
                        {$clearHeaderSortersJs}
                        {$clearHeaderFiltersJs}
                        {$clearSetupJs}
                        {$this->buildJsResetter()}
                    }
                }, {
                    text: {$this->escapeString($this->translate('WIDGET.DATACONFIGURATOR.BUTTON_APPLY'))},
                    iconCls: '{$this->buildCssIconClass(Icons::CHECK)}',
                    handler: function(){
                        $('#{$this->getIdOfConfiguratorDialog()}').dialog('close');
                        {$clearHeaderSortersJs}
                        {$syncHeaderFiltersJs}
                        {$dataEl->buildJsRefresh()}
                    }
                }, {
                    text: {$this->escapeString($this->translate('WIDGET.DATACONFIGURATOR.BUTTON_CANCEL'))},
                    iconCls: '{$this->buildCssIconClass(Icons::TIMES)}',
                    plain: true,
                    handler: function(){
                        $('#{$this->getIdOfConfiguratorDialog()}').dialog('close');
                    }
                }
            ],
            onOpen: function(){
                if (bMobile) {
                    jqDialog.dialog('maximize');
                }
                setTimeout(function(){
                    $('#{$this->getIdOfConfiguratorTabs()}').tabs('resize');
                    {$layoutFiltersJs}
                }, 0);
                {$refreshSetupsJs}
            }
        });
        // The `cls` option cannot be used here because easyui overrides it with `window` for every window
        jqDialog.dialog('panel').addClass('exf-data-configurator-dialog');
        $('#{$this->getIdOfConfiguratorTabs()}').tabs({fit: true, border: false{$layoutFiltersOnSelectJs}});
        {$this->buildJsFunctionPrefix()}UpdateConfiguratorBadges();
    }
    jqDialog.dialog('open');
    if (bShowSetups === true) {
        $('#{$this->getIdOfConfiguratorTabs()}').tabs('select', 0);
    }
    if (! bMobile) {
        jqDialog.dialog('center');
    }
}

JS;
    }

    /**
     * Builds the badge registry so only rendered configurator tabs are queried for counts.
     *
     * @return string
     */
    protected function buildJsConfiguratorBadgeFunctions() : string
    {
        $buttonId = $this->getIdOfConfiguratorButton($this->getFacade()->getElement($this->getWidget()->getWidgetConfigured())->getId());

        return <<<JS

var {$this->buildJsFunctionPrefix()}ConfiguratorBadges = [];

function {$this->buildJsFunctionPrefix()}RegisterConfiguratorBadge(sTabId, fnCount) {
    {$this->buildJsFunctionPrefix()}ConfiguratorBadges.push({tabId: sTabId, count: fnCount});
}

function {$this->buildJsFunctionPrefix()}UpdateConfiguratorBadges() {
    var iTotal = 0;
    var jqButton = $('#{$buttonId}');
    {$this->buildJsFunctionPrefix()}ConfiguratorBadges.forEach(function(oBadge){
        var iCount = Math.max(0, Number(oBadge.count()) || 0);
        var jqTab = $('#' + oBadge.tabId);
        var oTabOptions = jqTab.data('panel') !== undefined ? jqTab.panel('options') : null;
        var jqBadge;
        iTotal += iCount;
        if (oTabOptions === null || oTabOptions.tab === undefined) {
            return;
        }
        jqBadge = oTabOptions.tab.find('.exf-tab-badge');
        if (jqBadge.length === 0) {
            jqBadge = $('<span class="exf-tab-badge"></span>').appendTo(oTabOptions.tab.find('.tabs-title'));
        }
        jqBadge.text(iCount);
    });
    // An attribute survives easyui rebuilding the inner markup of the button
    if (iTotal > 0) {
        jqButton.attr('data-exf-badge', iTotal);
    } else {
        jqButton.removeAttr('data-exf-badge');
    }
}

JS;
    }

    /**
     * Refreshes the configurator tab and button badges.
     *
     * @return string
     */
    public function buildJsConfiguratorBadgesUpdate() : string
    {
        return $this->hasConfiguratorDialog() ? $this->buildJsFunctionPrefix() . 'UpdateConfiguratorBadges();' : '';
    }

    /**
     * Moves default header filters into the configurator and collapses their panel on smartphones.
     *
     * @return string
     */
    protected function buildJsResponsiveHeader() : string
    {
        if ($this->hasResponsiveHeader() === false || $this->hasTabFilters() === false) {
            return '';
        }

        $filterTabId = $this->getFacade()->getElement($this->getWidget()->getFilterTab())->getId();
        $collapseButtonJs = $this->btnCollaps === null
            ? ''
            : "$('#" . $this->getFacade()->getElement($this->btnCollaps)->getId() . "').hide();";

        return <<<JS

if (window.matchMedia('(max-width: 600px)').matches) {
    var jqResponsiveFilters = $('#{$filterTabId}');
    var jqResponsiveHeader = jqResponsiveFilters.closest('.exf-data-header');
    var iResponsiveHeaderInitAttempts = 0;
    $('#{$this->getIdOfFiltersTab()}').append(jqResponsiveFilters);
    (function fnCollapseResponsiveHeader(){
        if (jqResponsiveHeader.data('panel') === undefined) {
            if (iResponsiveHeaderInitAttempts++ < 100) {
                setTimeout(fnCollapseResponsiveHeader, 10);
            }
            return;
        }
        jqResponsiveHeader.panel('collapse', false);
    })();
    {$collapseButtonJs}
} else {
    $('#{$this->getIdOfResponsiveStaging()}').append($('#{$this->getIdOfFiltersTab()}'));
}

JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsConfiguratorDialogShow() : string
    {
        return $this->hasConfiguratorDialog() ? $this->buildJsFunctionPrefix() . 'ShowConfigurator();' : '';
    }
    
    /**
     * Initializes sorter handling independently from badge UI so sorting still applies to data loads
     * when no configurator dialog is rendered.
     *
     * @return string
     */
    protected function buildJsSorterBuilderInit() : string
    {
        if ($this->hasTabSorters() === false) {
            return '';
        }
        
        $attributes = [];
        foreach ($this->getSortableAttributes() as $alias => $caption) {
            $attributes[] = ['attribute_alias' => $alias, 'caption' => $caption];
        }
        $directions = [
            ['value' => SortingDirectionsDataType::ASC, 'text' => $this->translate('WIDGET.DATACONFIGURATOR.SORTER_ASC')],
            ['value' => SortingDirectionsDataType::DESC, 'text' => $this->translate('WIDGET.DATACONFIGURATOR.SORTER_DESC')]
        ];
        $i18n = [
            'add' => $this->translate('WIDGET.DATACONFIGURATOR.SORTER_ADD'),
            'remove' => $this->translate('WIDGET.DATACONFIGURATOR.SORTER_REMOVE'),
            'moveUp' => $this->translate('WIDGET.DATACONFIGURATOR.SORTER_MOVE_UP'),
            'moveDown' => $this->translate('WIDGET.DATACONFIGURATOR.SORTER_MOVE_DOWN'),
            'empty' => $this->translate('WIDGET.DATACONFIGURATOR.SORTERS_EMPTY')
        ];
        $registerBadgeJs = '';
        if ($this->hasConfiguratorDialog()) {
            $registerBadgeJs = <<<JS
{$this->buildJsFunctionPrefix()}RegisterConfiguratorBadge('{$this->getIdOfSortersTab()}', function(){
    var jqSorterBuilder = $('#{$this->getIdOfSorterBuilder()}');
    if (jqSorterBuilder.length === 0 || jqSorterBuilder.data('exfSorterBuilder') === undefined) {
        return 0;
    }
    return jqSorterBuilder.exfSorterBuilder('getSorters').length;
});
JS;
        }
        
        return <<<JS

$('#{$this->getIdOfSorterBuilder()}').exfSorterBuilder({
    attributes: {$this->encodeJson($attributes)},
    sorters: {$this->buildJsonForInitialSorters()},
    directions: {$this->encodeJson($directions)},
    i18n: {$this->encodeJson($i18n)},
    onChange: {$this->buildJsFunctionPrefix()}UpdateConfiguratorBadges
});
{$registerBadgeJs}

JS;
    }
    
    /**
     * Initializes advanced-search filtering independently from badge UI so data-load filters remain available.
     *
     * @return string
     */
    protected function buildJsConditionBuilderInit() : string
    {
        if ($this->hasTabAdvancedSearch() === false) {
            return '';
        }
        
        $fields = [];
        $parsers = [];
        foreach ($this->getSearchableAttributes() as $field) {
            $fields[] = ['expression' => $field['expression'], 'caption' => $field['caption']];
            $formatter = $this->getFacade()->getDataTypeFormatter($field['data_type']);
            $parsers[] = $this->encodeJson($field['expression']) . ': function(mVal, sComparator){ return ' . $formatter->buildJsFilterParser('mVal', 'sComparator') . '; }';
        }
        
        // Upper case to emphasize the central role of the logical operators
        $operators = [
            ['value' => EXF_LOGICAL_AND, 'text' => mb_strtoupper($this->translate('WIDGET.DATACONFIGURATOR.OPERATOR_AND'))],
            ['value' => EXF_LOGICAL_OR, 'text' => mb_strtoupper($this->translate('WIDGET.DATACONFIGURATOR.OPERATOR_OR'))]
        ];
        $i18n = [
            'addCondition' => $this->translate('WIDGET.DATACONFIGURATOR.CONDITION_ADD'),
            'addGroup' => $this->translate('WIDGET.DATACONFIGURATOR.CONDITION_GROUP_ADD'),
            'removeCondition' => $this->translate('WIDGET.DATACONFIGURATOR.CONDITION_REMOVE'),
            'removeGroup' => $this->translate('WIDGET.DATACONFIGURATOR.CONDITION_GROUP_REMOVE'),
            'empty' => $this->translate('WIDGET.DATACONFIGURATOR.CONDITIONS_EMPTY'),
            'valuePromptBetween' => $this->translate('WIDGET.DATACONFIGURATOR.CONDITION_VALUE_BETWEEN')
        ];
        $registerBadgeJs = '';
        if ($this->hasConfiguratorDialog()) {
            $registerBadgeJs = <<<JS
{$this->buildJsFunctionPrefix()}RegisterConfiguratorBadge('{$this->getIdOfAdvancedSearchTab()}', function(){
    var jqConditionBuilder = $('#{$this->getIdOfConditionBuilder()}');
    if (jqConditionBuilder.length === 0 || jqConditionBuilder.data('exfConditionBuilder') === undefined) {
        return 0;
    }
    var fnCount = function(oGroup){
        var iCount = 0;
        (oGroup.conditions || []).forEach(function(oCondition){
            if (oCondition.value !== null && oCondition.value !== '') {
                iCount++;
            }
        });
        (oGroup.nested_groups || []).forEach(function(oNested){
            iCount += fnCount(oNested);
        });
        return iCount;
    };
    return fnCount(jqConditionBuilder.exfConditionBuilder('getModel'));
});
JS;
        }
        
        return <<<JS

$('#{$this->getIdOfConditionBuilder()}').exfConditionBuilder({
    objectAlias: {$this->encodeJson($this->getWidget()->getMetaObject()->getAliasWithNamespace())},
    fields: {$this->encodeJson($fields)},
    comparators: {$this->buildJsonForComparators()},
    operators: {$this->encodeJson($operators)},
    defaultComparator: {$this->encodeJson(ComparatorDataType::IS)},
    parsers: {
        {$this->implodeJs($parsers)}
    },
    i18n: {$this->encodeJson($i18n)},
    onChange: {$this->buildJsFunctionPrefix()}UpdateConfiguratorBadges
});
{$registerBadgeJs}

JS;
    }
    
    /**
     * Adds the conditions of the advanced search tab to the filters of the configurator
     * 
     * {@inheritDoc}
     * @see JqueryDataConfiguratorTrait::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null, bool $unrendered = false)
    {
        $dataGetterJs = $this->buildJsDataGetterViaTrait($action, $unrendered);
        if ($unrendered === true || $this->hasTabAdvancedSearch() === false) {
            return $dataGetterJs;
        }
        
        return <<<JS
function(){
    var oData = {$dataGetterJs};
    var jqSearch = $('#{$this->getIdOfConditionBuilder()}');
    var oSearchGrp = jqSearch.data('exfConditionBuilder') !== undefined ? jqSearch.exfConditionBuilder('getConditionGroup') : null;
    if (oSearchGrp !== null) {
        if (oData.filters === undefined) {
            oData.filters = {operator: "AND", ignore_empty_values: true, conditions: [], nested_groups: []};
        }
        oData.filters.nested_groups = oData.filters.nested_groups || [];
        oData.filters.nested_groups.push(oSearchGrp);
    }
    return oData;
}()
JS;
    }
    
    /**
     * Adds the sorters of the sorting tab to the parameters of a data request
     * 
     * Sorting via column headers always wins: the data widget syncs it back into the sorting tab
     * via `buildJsSortersSetter()`, while applying the dialog clears the column header sorting.
     * 
     * @param string $paramJs
     * @return string
     */
    public function buildJsOnBeforeLoadAddSorters(string $paramJs = 'param') : string
    {
        if ($this->hasTabSorters() === false) {
            return '';
        }
        
        return <<<JS

                (function(oParams){
                    var jqSorters = $('#{$this->getIdOfSorterBuilder()}');
                    var aSorters;
                    if (oParams.sort !== undefined && oParams.sort !== null && oParams.sort !== '') {
                        return;
                    }
                    if (jqSorters.data('exfSorterBuilder') === undefined) {
                        return;
                    }
                    aSorters = jqSorters.exfSorterBuilder('getSorters');
                    if (aSorters.length === 0) {
                        return;
                    }
                    oParams.sortAttr = aSorters.map(function(oSorter){ return oSorter.attribute_alias; }).join(',');
                    oParams.order = aSorters.map(function(oSorter){ return oSorter.direction.toLowerCase(); }).join(',');
                })({$paramJs});

JS;
    }
    
    /**
     * Returns JS to replace the contents of the sorting tab with an array of `{attribute_alias, direction}`
     * 
     * This is what keeps the sorting tab in sync with the sorting via column headers.
     * 
     * @param string $aSortersJs
     * @return string
     */
    public function buildJsSortersSetter(string $aSortersJs) : string
    {
        if ($this->hasTabSorters() === false) {
            return '';
        }
        
        return <<<JS

                (function(aSorters){
                    var jqSorters = $('#{$this->getIdOfSorterBuilder()}');
                    if (jqSorters.data('exfSorterBuilder') === undefined) {
                        return;
                    }
                    // Avoid re-rendering the tab on every refresh - only real changes matter here
                    if (JSON.stringify(jqSorters.exfSorterBuilder('getSorters')) === JSON.stringify(aSorters)) {
                        return;
                    }
                    jqSorters.exfSorterBuilder('setSorters', aSorters);
                })({$aSortersJs});

JS;
    }
    
    /**
     * Returns a JS expression evaluating to the conditions of the top-most group of the advanced search
     * 
     * The array is empty if the top-most group is not an AND-group - such a configuration cannot be
     * represented by column filters, which are always combined with AND.
     * 
     * @return string
     */
    public function buildJsSearchConditionsGetter() : string
    {
        if ($this->hasTabAdvancedSearch() === false) {
            return '[]';
        }
        
        return <<<JS
function(){
                    var jqSearch = $('#{$this->getIdOfConditionBuilder()}');
                    var oModel;
                    if (jqSearch.data('exfConditionBuilder') === undefined) {
                        return [];
                    }
                    oModel = jqSearch.exfConditionBuilder('getModel');
                    return oModel.operator === '{$this->escapeString(EXF_LOGICAL_AND, false)}' ? oModel.conditions : [];
                }()
JS;
    }

    /**
     * Returns JS to merge an array of `{expression, comparator, value}` into the advanced search
     * 
     * Only the top-most condition group is touched and only if it is an AND-group. If it contains
     * multiple rows for the same expression, the first one is updated. Rows for expressions listed
     * in `$aManagedExpressionsJs` (i.e. those, that have a column filter) are removed if they are
     * not part of the passed conditions anymore.
     * 
     * @param string $aConditionsJs
     * @param string $aManagedExpressionsJs
     * @return string
     */
    public function buildJsSearchConditionsSetter(string $aConditionsJs, string $aManagedExpressionsJs) : string
    {
        if ($this->hasTabAdvancedSearch() === false) {
            return '';
        }
        
        return <<<JS

                (function(aConditions, aManaged){
                    var jqSearch = $('#{$this->getIdOfConditionBuilder()}');
                    var oModel, aTop, bChanged = false;
                    var fnFind = function(sExpression){
                        for (var i = 0; i < aTop.length; i++) {
                            if (aTop[i].expression === sExpression) {
                                return aTop[i];
                            }
                        }
                        return null;
                    };
                    if (jqSearch.data('exfConditionBuilder') === undefined) {
                        return;
                    }
                    oModel = jqSearch.exfConditionBuilder('getModel');
                    if (oModel.operator !== '{$this->escapeString(EXF_LOGICAL_AND, false)}') {
                        return;
                    }
                    aTop = oModel.conditions;
                    aConditions.forEach(function(oCondition){
                        var oRow = fnFind(oCondition.expression);
                        if (oRow === null) {
                            aTop.push({
                                expression: oCondition.expression,
                                comparator: oCondition.comparator,
                                value: oCondition.value
                            });
                            bChanged = true;
                        } else if (oRow.comparator !== oCondition.comparator || String(oRow.value) !== String(oCondition.value)) {
                            oRow.comparator = oCondition.comparator;
                            oRow.value = oCondition.value;
                            bChanged = true;
                        }
                    });
                    for (var i = aTop.length - 1; i >= 0; i--) {
                        var sExpression = aTop[i].expression;
                        // Incomplete rows belong to the user - they are never removed automatically
                        if (String(aTop[i].value || '') === '' || aManaged.indexOf(sExpression) === -1) {
                            continue;
                        }
                        if (aConditions.filter(function(oCondition){ return oCondition.expression === sExpression; }).length > 0) {
                            continue;
                        }
                        aTop.splice(i, 1);
                        bChanged = true;
                    }
                    if (bChanged) {
                        jqSearch.exfConditionBuilder('setModel', oModel);
                    }
                })({$aConditionsJs}, {$aManagedExpressionsJs});

JS;
    }
    
    /**
     * Also resets the sorters and the advanced search of the configurator dialog
     * 
     * {@inheritDoc}
     * @see JqueryDataConfiguratorTrait::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        $js = '';
        if ($this->hasTabSorters()) {
            $js .= <<<JS

                if ($('#{$this->getIdOfSorterBuilder()}').data('exfSorterBuilder') !== undefined) {
                    $('#{$this->getIdOfSorterBuilder()}').exfSorterBuilder('setSorters', {$this->buildJsonForInitialSorters()});
                }
JS;
        }
        if ($this->hasTabAdvancedSearch()) {
            $js .= <<<JS

                if ($('#{$this->getIdOfConditionBuilder()}').data('exfConditionBuilder') !== undefined) {
                    $('#{$this->getIdOfConditionBuilder()}').exfConditionBuilder('clear');
                }
JS;
        }
        return $js . ';' . $this->buildJsResetterViaTrait();
    }
    
    /**
     * Returns an array of `attribute_alias => caption` for all attributes, that can be sorted over
     * 
     * @return string[]
     */
    protected function getSortableAttributes() : array
    {
        if ($this->sortableAttributes !== null) {
            return $this->sortableAttributes;
        }

        $attrs = [];
        foreach ($this->getWidget()->getSortableAttributes() as $title => $alias) {
            $attrs[$alias] = $title;
        }
        
        $this->sortableAttributes = $attrs;
        return $this->sortableAttributes;
    }
    
    /**
     * Returns an array of `['expression' => ..., 'caption' => ..., 'data_type' => ...]` for the advanced search
     * 
     * Everything the user can see in the data widget or in the quick filters can be searched over: all
     * filterable columns and all visible filters. Filters over relations are turned into filters over the
     * label of the related object because the advanced search only offers plain text input.
     * 
     * @return array[]
     */
    protected function getSearchableAttributes() : array
    {
        if ($this->searchableFields !== null) {
            return $this->searchableFields;
        }

        $obj = $this->getWidget()->getDataWidget()->getMetaObject();
        $fields = [];
        foreach ($this->getWidget()->getFilterableAttributes() as $title => $alias) {
            $fields[$title] = [
                'expression' => $alias,
                'caption' => $title,
                'data_type' => $obj->getAttribute($alias)->getDataType()
            ];
        }
        
        ksort($fields);
        $this->searchableFields = array_values($fields);
        return $this->searchableFields;
    }
    
    /**
     * 
     * @param string $attributeAlias
     * @return string
     */
    protected function getAttributeCaption(string $attributeAlias) : string
    {
        $object = $this->getWidget()->getMetaObject();
        if (! $object->hasAttribute($attributeAlias)) {
            return $attributeAlias;
        }
        $attr = $object->getAttribute($attributeAlias);
        if ($attr->isRelated()) {
            $objName = $attr->getRelationPath()->getRelationLast()->getName();
            return $objName !== $attr->getName() ? $attr->getName() . ' (' . $attr->getObject()->getName() . ')' : $attr->getName();
        }
        return $attr->getName();
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsonForInitialSorters() : string
    {
        $sorters = [];
        foreach ($this->getWidget()->getDataWidget()->getSorters() as $sorter) {
            $sorters[] = [
                'attribute_alias' => $sorter->getProperty('attribute_alias'),
                'direction' => strtoupper($sorter->getProperty('direction') ?? SortingDirectionsDataType::ASC)
            ];
        }
        return $this->encodeJson($sorters);
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsonForComparators() : string
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $comparators = [];
        foreach ([
            'IS' => ComparatorDataType::IS,
            'IS_NOT' => ComparatorDataType::IS_NOT,
            'EQUALS' => ComparatorDataType::EQUALS,
            'EQUALS_NOT' => ComparatorDataType::EQUALS_NOT,
            'LESS_THAN' => ComparatorDataType::LESS_THAN,
            'LESS_THAN_OR_EQUALS' => ComparatorDataType::LESS_THAN_OR_EQUALS,
            'GREATER_THAN' => ComparatorDataType::GREATER_THAN,
            'GREATER_THAN_OR_EQUALS' => ComparatorDataType::GREATER_THAN_OR_EQUALS,
            'IN' => ComparatorDataType::IN,
            'NOT_IN' => ComparatorDataType::NOT_IN,
            'BETWEEN' => ComparatorDataType::BETWEEN,
        ] as $constant => $comparator) {
            $comparators[] = [
                'value' => $comparator,
                'text' => $translator->translate('GLOBAL.COMPARATOR.' . $constant . '_NAME'),
                'hint' => $translator->translate('GLOBAL.COMPARATOR.' . $constant . '_HINT')
            ];
        }
        return $this->encodeJson($comparators);
    }
    
    /**
     * 
     * @param mixed $data
     * @return string
     */
    protected function encodeJson($data) : string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * 
     * @param string[] $lines
     * @return string
     */
    protected function implodeJs(array $lines) : string
    {
        return implode(",\n        ", $lines);
    }
}