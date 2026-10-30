<?php
namespace exface\JEasyUIFacade\Facades\Elements;

use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Facades\AbstractAjaxFacade\Elements\MarkianTrait;
use exface\Core\Widgets\Markdown;

/**
 * Displays exact Markdown through the sanitized, rendering-only Markian viewer.
 *
 * PHP never converts the document to HTML. Source remains available for binding
 * and prefill, while the viewer owns only disposable rendered output.
 */
class EuiMarkdown extends EuiHtml
{
    use MarkianTrait;

    /**
     * Builds an empty viewer mount without invoking the PHP Markdown converter.
     *
     * {@inheritDoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryHtmlTrait::buildHtml()
     */
    public function buildHtml()
    {
        return $this->buildHtmlLabelWrapper('<div id="' . $this->getId() . '" class="exf-markdown"></div>');
    }

    /**
     * Registers normal value/live-reference behavior without legacy Mermaid scans.
     *
     * {@inheritDoc}
     *
     * @see EuiHtml::init()
     */
    protected function init()
    {
        EuiValue::init();
    }
    
    /**
     * {@inheritDoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildHtmlHeadTags()
     */
    public function buildHtmlHeadTags()
    {
        return $this->buildHtmlMarkianHeadTags(true);
    }
    
    /**
     * {@inheritDoc}
     *
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiValue::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        return parent::buildCssElementClass() . ' exf-markdown';
    }
    
    /**
     * {@inheritDoc}
     *
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiValue::buildJs()
     */
    public function buildJs()
    {
        $widget = $this->getWidget();
        $type = $widget->getValueDataType();
        $options = [
            'markdown' => $widget->getValueWidgetLink() ? '' : (string) ($widget->getValueWithDefaults() ?? ''),
            'markdownCssUrls' => [$this->getFacade()->buildUrlToSource('LIBS.MARKDOWN.CSS')],
            'markdownCssClasses' => ['markdown-body'],
            'softbreaks' => $type instanceof MarkdownDataType ? $type->getEnableNewlines() : true
        ];
        if ($widget instanceof Markdown && $widget->getBaseUrl()) {
            $options['documentBase'] = $widget->getBaseUrl();
        }
        $optionsJs = json_encode($options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        // Override navigation only for new-tab/popup links; fragment links still scroll within this viewer.
        if ($widget instanceof Markdown && $widget->getOpenLinksIn() !== Markdown::OPEN_LINKS_IN_SELF) {
            $openJs = $widget->getOpenLinksIn() === Markdown::OPEN_LINKS_IN_NEW_TAB
                ? $this->buildJsOpenLinkInNewTab('link.href')
                : $this->buildJsOpenLinkInPopup('link.href', $widget->getOpenLinksInPopupWidth(), $widget->getOpenLinksInPopupHeight());
            $optionsJs = <<<JS
Object.assign({$optionsJs}, {onLinkOpen: function(context){
    var link = context.event.target.closest('a');
    if (context.destination.startsWith('#')) {
        var id = decodeURIComponent(context.destination.slice(1));
        var target = Array.from(context.event.currentTarget.querySelectorAll('[id]')).find(function(node){return node.id === id;});
        if (target) target.scrollIntoView({block: 'nearest'});
        return;
    }
    {$openJs}
}})
JS;
        }
        $sourceJs = $widget->hasRenderMermaidDiagrams()
            ? $this->escapeMarkdownForInlineJavaScript($this->getFacade()->buildUrlToSource('LIBS.MERMAID.JS'))
            : 'null';
        return 'MarkianAdapter.mount(document.getElementById(' . json_encode($this->getId()) . '), ' . $optionsJs . ', true, ' . $sourceJs . ');'
            . $this->buildJsEventScripts();
    }

    /**
     * {@inheritDoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDestroy()
     */
    public function buildJsDestroy() : string
    {
        return $this->buildJsMarkdownRemove();
    }

    /**
     * It creates JavaScript that opens a URL with a specified width and height in a simple pop-up window.
     *
     * @param $url
     * @param $width
     * @param $height
     * @return string
     */
    protected function buildJsOpenLinkInPopup($url, $width, $height) : string
    {
        
        $widthTag = $width ? 'width=' . $width . ',' : '';
        $heightTag = $height ? 'height=' . $height . ',' : '';
        return <<<JS

        window.open({$url}, 'window', '{$widthTag} {$heightTag} toolbar=no, menubar=no, resizable=yes');
JS;
    }

    /**
     * It creates JavaScript that opens a URL in a new browser tab.
     *
     * @param $url
     * @return string
     */
    protected function buildJsOpenLinkInNewTab($url) : string
    {
        return <<<JS
        
          window.open({$url}, '_blank');
JS;
    }
}