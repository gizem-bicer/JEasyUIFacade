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
    use EuiDataElementTrait;
    
    public function buildHtmlHeadTags()
    {
        $headers = array_merge(parent::buildHtmlHeadTags(), $this->buildHtmlHeadTagsForComments());
        
        return $headers;
    }

    protected function buildHtmlHeadTagsForComments() : array
    {
        return [
            '<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.textcomplete/1.8.0/jquery.textcomplete.js"></script>',
            '<script type="text/javascript" src="vendor/bower-asset/jquery-comments/js/jquery-comments.js"></script>',
            '<link rel="stylesheet" type="text/css" href="vendor/bower-asset/jquery-comments/css/jquery-comments.css">'
        ];
    }

    public function buildHtml()
    {
        $chart_panel_options = "title: '{$this->getCaption()}'";
        $gridItemClass = $this->getMasonryItemClass() . ' exf-commentsfeed exf-data-widget';
        if ($this->getWidget()->getHideHeader()) {
            $gridItemClass .= ' exf-data-hide-header';
        }
        $panel = <<<HTML

        <div class="exf-grid-item {$gridItemClass}" style="width:{$this->getWidth()};min-width:{$this->getMinWidth()};padding:{$this->getPadding()};box-sizing:border-box;">
    
    	   {$this->buildHtmlComments()}
        </div>

HTML;
    
        return $panel;
    }

    protected function buildHtmlComments() : string
    {
        return "<div id=\"{$this->getId()}\"></div>";
    }

    /**
     * Builds facade JavaScript around the shared comments configuration to keep adapter-specific output minimal.
     *
     * @return string
     */
    function buildJs()
    {
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
    }
    
    {$this->buildJsDataLoadFunction()}

    {$this->buildJsFunctionPrefix()}_init();

    {$this->buildJsButtons()}

JS;
    }

    /**
     * Connects facade-specific server failures to jQuery Comments because the shared trait handles successful actions by event.
     *
     * @return void
     */
    protected function registerCommentsFeedErrorCallbacks() : void
    {
        $elementSelector = $this->escapeString($this->getId(), true, false);
        foreach ([
            $this->getWidget()->getButtonCreate(),
            $this->getWidget()->getButtonEdit(),
            $this->getWidget()->getButtonDelete()
        ] as $buttonWidget) {
            /** @var EuiButton $buttonElement */
            $buttonElement = $this->getFacade()->getElement($buttonWidget);
            $buttonElement->addOnErrorScript("$('#{$elementSelector}')[0].errorCallback()");
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
        
        return <<<JS

    // Don't load if already loading    
        if ($('#{$this->getId()}').data('_loading')) return;	
        
        $('#{$this->getId()}').data('_loading', 1);

        var param = {
            action: '{$widget->getLazyLoadingActionAlias()}',
            resource: "{$widget->getPage()->getAliasWithNamespace()}",
            element: "{$widget->getId()}",
            object: "{$widget->getMetaObject()->getId()}"
        };

        var checkOnBeforeLoad = function(param){
            {$this->buildJsOnBeforeLoadScript('param')}
            {$this->buildJsOnBeforeLoadAddConfiguratorData('param')}
        }(param);

        if (checkOnBeforeLoad === false) {
            return;
        }

        param = $.extend(param, ({$oParamsJs} || {}));
        $.ajax({
        url: "{$this->getAjaxUrl()}",
        data: param,
        method: 'POST',
        success: function(json){
                var aRowsMod = [];
                try {

                    {$this->buildJsLoadFilterHandleWidgetLinks('json.rows')}
                        
                    json.rows.forEach(function(oRow, i){
                        var oRowMod = oRow;
                        oRowMod._modified = oRow['{$this->getWidget()->getCommentEditedDateColumn()->getDataColumnName()}'];
                        aRowsMod.push(oRowMod);
                    });
                    {$successJs} (aRowsMod);
                    
                    $('#{$this->getId()}').data('_loading', 0);

                } catch (err) {
                    console.error(err);
                }
            },
            error: function(jqXHR, textStatus, errorThrown){
                $('#{$this->getId()}').data('_loading', 0);
                {$this->buildJsShowErrorAjax('jqXHR')}
                //TODO call error callback
                {$errorJs} (jqXHR, textStatus, errorThrown);
            }
        });
	
JS;
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
        return <<<JS

            {$this->buildJsLoadFilterHandleWidgetLinks('oParams.data')}                

            $.ajax({
                url: "{$this->getAjaxUrl()}",
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
        return $this->buildJsBusyIconHide() . "$('#{$this->getId()}').prepend('<div class=\"imagecarousel-loading\"><div class=\"datagrid-mask\" style=\"display:block\"></div><div class=\"datagrid-mask-msg\" style=\"display: block; left: 50%; height: 16px; margin-left: -107.555px; line-height: 16px;\"></div></div>');";
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiAbstractElement::buildJsBusyIconHide()
     */
    public function buildJsBusyIconHide()
    {
        return "$('#{$this->getId()} .imagecarousel-loading').remove();";
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
                return "($('#{$this->getId()}').data('_exfData') || {oId: '{$widget->getMetaObject()->getId()}', rows: []})";
                
            // If the button requires none of the rows explicitly
            case $customMode === DataButton::INPUT_ROWS_NONE:
                return '{}';

            default:
                return <<<JS

                    (function(jqComments){
                        var oInputData = jqComments.data('_exfInput');
                        var oData = {
                            oId: "{$widget->getMetaObject()->getId()}",
                            rows: [
                                oInputData
                            ]
                        };
                        {$this->buildJsLoadFilterHandleWidgetLinks('oData')};
                        return oData;
                    })($('#{$this->getId()}'))
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
        return "$('#{$this->getId()}').data('comments').fetchDataAndRender()";
    }
}