<?php
namespace exface\JEasyUIFacade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryCommentsTrait;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsUploaderTrait;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\DataButton;
use exface\Core\Widgets\Parts\Uploader;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsDateFormatter;
use exface\JEasyUIFacade\Facades\Elements\Traits\EuiDataElementTrait;

/**
 * Uses jQuery Comments by Viima to implement the CommentsFeed widget
 * 
 * @link https://viima.github.io/jquery-comments/
 * 
 * @author Andrej Kabachnik
 * 
 * @method \exface\Core\Widgets\CommentsFeed getWidget() 
 */
class EuiCommentsFeed extends EuiData
{    
    use JsUploaderTrait;
    use JqueryCommentsTrait;
    use EuiDataElementTrait {
        init as initDataElement;
    }

    private $linkedFilterHandlersRegistered = false;

    /**
     * Enables the standard linked-filter refresh before the configurator registers its change handlers.
     *
     * @return void
     */
    protected function init()
    {
        $this->initDataElement();
        foreach ($this->getWidget()->getFilters() as $filter) {
            if ($filter->getValueWidgetLink() !== null) {
                $filter->setApplyOnChange(true);
            }
        }
    }
    
    public function buildHtmlHeadTags()
    {
        $headers = array_merge(parent::buildHtmlHeadTags(), $this->buildHtmlHeadTagsForComments());
        
        return $headers;
    }

    /**
     * Keeps the feed's assets local so disabled mentions never trigger a blocked third-party script request.
     *
     * @return array
     */
    protected function buildHtmlHeadTagsForComments() : array
    {
        return [
            '<script type="text/javascript" src="vendor/bower-asset/jquery-comments/js/jquery-comments.js"></script>',
            '<link rel="stylesheet" type="text/css" href="vendor/bower-asset/jquery-comments/css/jquery-comments.css">'
        ];
    }

    /**
     * Reuses the standard data header to render hidden filter inputs while preserving the feed's headerless markup.
     *
     * @return string
     */
    public function buildHtml()
    {
        $gridItemClass = $this->getMasonryItemClass() . ' exf-commentsfeed exf-data-widget';
        if ($this->getWidget()->getHideHeader()) {
            $gridItemClass .= ' exf-data-hide-header';
        }
        $headerHtml = $this->buildHtmlTableHeader();

        return <<<HTML

        <div class="exf-grid-item {$gridItemClass}" style="width:{$this->getWidth()};min-width:{$this->getMinWidth()};padding:{$this->getPadding()};box-sizing:border-box;">
            {$headerHtml}
            {$this->buildHtmlComments()}
        </div>

HTML;
    }

    protected function buildHtmlComments() : string
    {
        return "<div id=\"{$this->getId()}\"></div>";
    }

    /**
     * Builds the comments integration after wiring linked filters to refresh and update create availability.
     *
     * @return string
     */
    function buildJs()
    {
        $this->registerLinkedFilterStateHandlers();
        $this->registerCommentsFeedErrorCallbacks();
        /** @var EuiButton $createButton */
        $createButton = $this->getFacade()->getElement($this->getWidget()->getButtonCreate());
        /** @var EuiButton $editButton */
        $editButton = $this->getFacade()->getElement($this->getWidget()->getButtonEdit());
        /** @var EuiButton $deleteButton */
        $deleteButton = $this->getFacade()->getElement($this->getWidget()->getButtonDelete());

        return <<<JS

    {$this->buildJsForPanel()}

    function {$this->buildJsFunctionPrefix()}_init(){
        {$this->buildJsJqueryCommentsInit(
            $createButton->buildJsClickFunctionName(),
            $editButton->buildJsClickFunctionName(),
            $deleteButton->buildJsClickFunctionName()
        )}
        {$this->buildJsUpdateReadOnlyState()}
    }
    
    {$this->buildJsDataLoadFunction()}

    {$this->buildJsFunctionPrefix()}_init();

    {$this->buildJsButtons()}

JS;
    }

    /**
     * Registers state updates on linked filters so the existing apply-on-change refresh also unlocks new comments.
     *
     * @return void
     */
    protected function registerLinkedFilterStateHandlers() : void
    {
        if ($this->linkedFilterHandlersRegistered === true) {
            return;
        }
        $updateStateJs = $this->buildJsUpdateReadOnlyState();
        foreach ($this->getWidget()->getFilters() as $filter) {
            if ($filter->getValueWidgetLink() !== null) {
                $this->getFacade()->getElement($filter)->addOnChangeScript($updateStateJs);
            }
        }
        $this->linkedFilterHandlersRegistered = true;
    }

    /**
     * Hides comment creation while required filters are invalid, as in a create dialog without a parent id.
     *
     * @return string
     */
    protected function buildJsUpdateReadOnlyState() : string
    {
        $elementSelector = '$(' . $this->escapeString('#' . $this->getId(), true, false) . ')';
        $configuratorElement = $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget());
        return "{$elementSelector}.toggleClass('read-only', ! ({$configuratorElement->buildJsValidator()}));";
    }

    /**
     * Connects facade-specific server failures to jQuery Comments because the shared trait handles successful actions by event.
     *
     * @return void
     */
    protected function registerCommentsFeedErrorCallbacks() : void
    {
        $elementSelector = '$(' . $this->escapeString('#' . $this->getId(), true, false) . ')';
        foreach ([
            $this->getWidget()->getButtonCreate(),
            $this->getWidget()->getButtonEdit(),
            $this->getWidget()->getButtonDelete()
        ] as $buttonWidget) {
            /** @var EuiButton $buttonElement */
            $buttonElement = $this->getFacade()->getElement($buttonWidget);
            $buttonElement->addOnErrorScript("{$elementSelector}[0].errorCallback()");
        }
    }

    protected function buildJsDataLoaderOnLoaded(string $dataJs) : string
    {
        return '';
    }

    /**
     * Loads comment rows through the facade data action so the library receives its configured field mappings.
     *
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiData::buildJsDataSource()
     */
    public function buildJsDataLoadFunctionBody(string $oParamsJs = 'oParams', string $successJs = 'success', string $errorJs = 'error') : string
    {
        $widget = $this->getWidget();
        $elementSelector = '$(' . $this->escapeString('#' . $this->getId(), true, false) . ')';
        $actionAlias = $this->escapeString($widget->getLazyLoadingActionAlias(), true, false);
        $pageAlias = $this->escapeString($widget->getPage()->getAliasWithNamespace(), true, false);
        $widgetId = $this->escapeString($widget->getId(), true, false);
        $objectId = $this->escapeString($widget->getMetaObject()->getId(), true, false);
        $ajaxUrl = $this->escapeString($this->getAjaxUrl(), true, false);
        $commentEditedColumn = $this->escapeString($widget->getCommentEditedDateColumn()->getDataColumnName(), true, false);
        
        return <<<JS

        var jqCommentsFeed = {$elementSelector};
        var finishSuccess = function(aRows) {
            jqCommentsFeed.data('_loading', 0);
            {$successJs}(aRows || []);
        };
        var finishError = function() {
            jqCommentsFeed.data('_loading', 0);
            if (typeof {$errorJs} === 'function') {
                {$errorJs}.apply(null, arguments);
            }
        };

        if (jqCommentsFeed.data('_loading')) {
            finishSuccess([]);
            return;
        }
        jqCommentsFeed.data('_loading', 1);

        try {
            var param = {
                action: {$actionAlias},
                resource: {$pageAlias},
                element: {$widgetId},
                object: {$objectId}
            };

            var checkOnBeforeLoad = function(param){
                {$this->buildJsOnBeforeLoadScript('param')}
                {$this->buildJsOnBeforeLoadAddConfiguratorData('param')}
            }.call(jqCommentsFeed[0], param);

            if (checkOnBeforeLoad === false) {
                finishSuccess([]);
                return;
            }

            param = $.extend(param, ({$oParamsJs} || {}));
            $.ajax({
                url: {$ajaxUrl},
                data: param,
                method: 'POST',
                success: function(json){
                    var aRowsMod = [];
                    try {
                        {$this->buildJsLoadFilterHandleWidgetLinks('json.rows')}

                        json.rows.forEach(function(oRow){
                            var oRowMod = oRow;
                            oRowMod._modified = oRow[{$commentEditedColumn}];
                            aRowsMod.push(oRowMod);
                        });
                        finishSuccess(aRowsMod);
                    } catch (err) {
                        console.error(err);
                        finishError(err);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown){
                    try {
                        {$this->buildJsShowErrorAjax('jqXHR')}
                    } finally {
                        finishError(jqXHR, textStatus, errorThrown);
                    }
                }
            });
        } catch (err) {
            console.error(err);
            finishError(err);
        }
	
JS;
    }

    /**
     * Keeps a new parent dialog's feed empty instead of showing an invalid-filter warning before the parent has an id.
     *
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiData::buildJsAutoloadDisabledMessageShow()
     */
    protected function buildJsAutoloadDisabledMessageShow(string $text = null) : string
    {
        return '';
    }

    protected function buildJsCommentsData() : string
    {
        // TODO
        return '';
    }
    
    /**
     *
     * @see SlickGalleryTrait::buildJsUploadSend()
     */
    protected function buildJsUploadSend(string $oParamsJs, string $onUploadCompleteJs) : string
    {
        $ajaxUrl = $this->escapeString($this->getAjaxUrl(), true, false);
        return <<<JS

            {$this->buildJsLoadFilterHandleWidgetLinks('oParams.data')}                

            $.ajax({
                url: {$ajaxUrl},
                data: $oParamsJs,
                method: 'POST',
                success: function(json){
                    {$onUploadCompleteJs}
                },
                error: function(jqXHR, textStatus,errorThrown){
                    {$this->buildJsBusyIconHide()}
                    {$this->buildJsShowErrorAjax('jqXHR')}
                    {$this->buildJsRefresh(true)}
                }
            });

JS;
    }
    
    /**
     *
     * @return JsDateFormatter
     */
    protected function getDateFormatter() : JsDateFormatter
    {
        return new JsDateFormatter(DataTypeFactory::createFromString($this->getWorkbench(), DateTimeDataType::class));
    }
    
    /**
     * Generates the acceptedFileTypes option with a corresponding regular expressions if allowed_extensions is set
     * for the widget
     *
     * @return string
     */
    protected function buildJsUploadAcceptedFileTypesFilter()
    {
        $uploader = $this->getWidget()->getUploader();
        if ($uploader->getAllowedFileExtensions()) {
            return 'acceptFileTypes: /(\.|\/)(' . str_replace(array(
                ',',
                ' '
            ), array(
                '|',
                ''
            ), $uploader->getAllowedFileExtensions()) . ')$/i,';
        } else {
            return '';
        }
    }
		   
    /**
     *
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiAbstractElement::buildJsBusyIconShow()
     */
    public function buildJsBusyIconShow()
    {
        $elementSelector = '$(' . $this->escapeString('#' . $this->getId(), true, false) . ')';
        return $this->buildJsBusyIconHide() . "{$elementSelector}.prepend('<div class=\"imagecarousel-loading\"><div class=\"datagrid-mask\" style=\"display:block\"></div><div class=\"datagrid-mask-msg\" style=\"display: block; left: 50%; height: 16px; margin-left: -107.555px; line-height: 16px;\"></div></div>');";
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiAbstractElement::buildJsBusyIconHide()
     */
    public function buildJsBusyIconHide()
    {
        $elementSelector = '$(' . $this->escapeString('#' . $this->getId() . ' .imagecarousel-loading', true, false) . ')';
        return "{$elementSelector}.remove();";
    }
    
    /**
     * 
     * @see JsUploaderTrait::getUploader()
     */
    protected function getUploader() : Uploader
    {
        return $this->getWidget()->getUploader();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiData::buildJsResize()
     */
    protected function buildJsResize() : string
    {
        return $this->buildJsResizeInnerWidget();
    }
    
    /**
     * Supplies comment action data from the currently edited model without logging its contents to the browser console.
     *
     * @see AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        $widget = $this->getWidget();
        $dataObj = $this->getMetaObjectForDataGetter($action);
        $elementSelector = '$(' . $this->escapeString('#' . $this->getId(), true, false) . ')';
        $objectId = $this->escapeString($widget->getMetaObject()->getId(), true, false);
        // Determine the columns we need in the actions data
        $colNamesList = implode(',', $widget->getActionDataColumnNames());
        
        if ($action !== null && $action->isDefinedInWidget() && $action->getWidgetDefinedIn() instanceof DataButton) {
            $customMode = $action->getWidgetDefinedIn()->getInputRows();
        } else {
            $customMode = null;
        }
        
        switch (true) {
            // If no action or explicitly requested ALL rows, return all loaded data
            // TODO save the data loaded from the server somewhere and return it here
            case $customMode === DataButton::INPUT_ROWS_ALL:
            case $action === null:
                return "({$elementSelector}.data('_exfData') || {oId: {$objectId}, rows: []})";
                
            // If the button requires none of the rows explicitly
            case $customMode === DataButton::INPUT_ROWS_NONE:
                return '{}';

            default:
                return <<<JS

                    (function(jqComments){
                        var oInputData = jqComments.data('_exfInput');
                        var oData = {
                            oId: {$objectId},
                            rows: [
                                oInputData
                            ]
                        };
                        {$this->buildJsLoadFilterHandleWidgetLinks('oData')};
                        return oData;
                    })({$elementSelector})
JS;
        }
    }

    /**
     * Function to refresh the chart
     *
     * @return string
     */
    public function buildJsRefresh() : string
    {
        $elementSelector = '$(' . $this->escapeString('#' . $this->getId(), true, false) . ')';
        return "{$elementSelector}.data('comments').fetchDataAndRender()";
    }
}