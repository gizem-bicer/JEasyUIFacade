<?php
namespace exface\JEasyUIFacade\Facades\Elements;

use exface\Core\Widgets\DialogHeader;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\WidgetGroup;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryMasonryGridTrait;

/**
 *
 * @method DialogHeader getWidget()
 * @author Andrej Kabachnik
 *        
 */
class EuiDialogHeader extends EuiWidgetGrid
{
    /**
     * 
     * @return void
     */
    protected function init()
    {
        parent::init();
        $widget = $this->getWidget();
        $caption = $this->getCaption();
        $widget->setHideCaption(true);
        
        if ($caption) {
            $heading = WidgetFactory::createFromUxon($widget->getPage(), new UxonObject([
                'widget_type' => 'TextHeading',
                'text' => $caption,
                'width' => 'max',
                'heading_level' => 2
            ]), $widget);
            
            if ($widget->hasWidgets() && $widget->getWidgetFirst() instanceof WidgetGroup) {
                $widget->getWidgetFirst()->addWidget($heading, 0);
            } else {
                $widget->addWidget($heading, 0);
            }
        }
    }

    /**
     * Returns the HTML for a jEasyUI layout region panel
     * 
     * @link https://www.jeasyui.com/documentation/index.php > layout
     * 
     * @return string
     */
    public function buildHtmlLayoutRegion() : string
    {
        return <<<HTML

                    <div data-options="region:'north'" class="exf-dialog-header" style="height: {$this->getHeight()}">
                        {$this->buildHtml()}
                    </div>
HTML;
    }
    
    public function buildCssElementClass()
    {
        return 'exf-dialog-header exf-element';
    }
    
    /**
     * After the regular grid layouter finishes, the header will adjust the size of its parent
     * jEasyUI layout element 
     * 
     * @see JqueryMasonryGridTrait::buildJsLayouter()
     */
    public function buildJsLayouter() : string
    {
        $dialogEl = $this->getFacade()->getElement($this->getWidget()->getDialog());
        if ($dialogEl->isLayout()) {
            $panelSelectorJs = "$('#{$this->getId()}').parents('.easyui-layout').first().layout('panel','north')";
            // TODO how to resize the north panel properly? It does not seem to work if there are tabs in the
            // center. Here is a suggestion: https://www.jeasyui.com/forum/index.php?topic=6325.0. But resizing
            // was actually reverted by calling `layout('resize')`!
        } else {
            $panelSelectorJs = "$('#{$this->getId()}')";
            $setBodyHeightJs = <<<JS

                    var jqBody = jqDialog.find('.exf-dialog-body');
                    jqBody.height(jqDialog.height() - jqDialog.find('.exf-dialog-header').height());
                    if (jqDialog.hasClass('exf-dialog-with-tabs')) {
                        jqDialog.find('.tabs-container').tabs('resize'); 
                    }
JS;
        }
        return parent::buildJsLayouter() . <<<JS

            setTimeout(function(){
                var jqDialog = $('#{$dialogEl->getId()}'); 
                var jqPanel = {$panelSelectorJs};
                if (! jqPanel) return;
                var jqGrid = jqPanel.find('.grid');
                if (! jqGrid) return;
                var iHeight = jqGrid.outerHeight() + 20;
                var fDiff = jqPanel.height() - iHeight;
                if (fDiff > 1) {
                    jqPanel.height(Math.round(iHeight));
                    {$setBodyHeightJs}
                }
            }, 0);
JS;
    }
}