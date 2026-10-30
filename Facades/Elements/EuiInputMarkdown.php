<?php
namespace exface\JEasyUIFacade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\MarkianTrait;
use exface\Core\Widgets\InputMarkdown;

/**
 * JEasyUI implementation of the corresponding widget.
 *
 * @see InputMarkdown
 *
 * @method \exface\Core\Widgets\InputMarkdown getWidget()
 */
class EuiInputMarkdown extends EuiInput
{
    use MarkianTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiInput::getElementType()
     */
    public function getElementType() : ?string
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssHeightDefaultValue()
     */
    protected function buildCssHeightDefaultValue()
    {
        return ($this->getHeightRelativeUnit() * 4) . 'px';
    }

    /**
     * {@inheritDoc}
     *
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiInput::buildHtml()
     */
    public function buildHtml()
    {
        return $this->buildHtmlLabelWrapper('<div id="' . $this->getId() . '" class="exf-input-markdown" style="height:100%;min-height:260px"></div>');
    }

    /**
     * {@inheritDoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildHtmlHeadTags()
     */
    public function buildHtmlHeadTags()
    {
        return $this->buildHtmlMarkianHeadTags();
    }
    
    /**
     * {@inheritDoc}
     *
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiInput::buildJs()
     */
    public function buildJs()
    {
        $warning = (!empty($this->getWidget()->getMentions()) || !empty($this->getWidget()->getStencils()))
            ? "console.warn('Markian mention and stencil controls are not available yet; existing document source is retained.');"
            : '';
        return $warning . $this->buildJsMarkdownInitEditor() . ';' . $this->buildJsEventScripts();
    }

    /**
     * {@inheritDoc}
     *
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiInput::buildJsValidator()
     */
    public function buildJsValidator(?string $valJs = null) : string
    {
        $validator = $this->buildJsValidatorViaTrait($valJs);
        $messageJs = $this->escapeMarkdownForInlineJavaScript($this->getValidationErrorText());
        return <<<JS
(function(){
    var valid = ({$validator});
    var host = document.getElementById({$this->escapeMarkdownForInlineJavaScript($this->getId())});
    if (host) {
        var root = host.querySelector('[data-markian]') || host;
        root.setAttribute('aria-invalid', valid ? 'false' : 'true');
        if (valid) root.removeAttribute('title'); else root.setAttribute('title', {$messageJs});
    }
    return valid;
})()
JS;
    }

    /**
     * {@inheritDoc}
     *
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiInput::buildJsSetDisabled()
     */
    public function buildJsSetDisabled(bool $trueOrFalse) : string
    {
        return '(function(){var e = ' . $this->buildJsMarkdownVar() . '; if(e) e.setDisabled(' . ($trueOrFalse ? 'true' : 'false') . ');})()';
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiInput::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        return parent::buildCssElementClass() . ' exf-input-markdown';
    }

    /**
     * {@inheritDoc}
     *
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiInput::buildJsRequiredGetter()
     */
    protected function buildJsRequiredGetter() : string
    {
        $default = $this->getWidget()->isRequired() ? 'true' : 'false';
        return '(function(){var s = $(' . json_encode('#' . $this->getId()) . ').attr("aria-required"); return s === undefined ? ' . $default . ' : s === "true";})()';
    }

    /**
     * Stores conditional required state without calling a nonexistent textbox plugin.
     *
     * {@inheritDoc}
     *
     * @see EuiInput::buildJsSetRequired()
     */
    protected function buildJsSetRequired(bool $required) : string
    {
        return '$(' . json_encode('#' . $this->getId()) . ').attr("aria-required", "' . ($required ? 'true' : 'false') . '");';
    }

    /**
     * Routes only document changes, not bubbled toolbar input changes, to actions.
     *
     * {@inheritDoc}
     *
     * @see EuiInput::buildJsOnChangeHandler()
     */
    protected function buildJsOnChangeHandler()
    {
        return '$(' . json_encode('#' . $this->getId()) . ').off("change.exfMarkian").on("change.exfMarkian", function(event){'
            . 'if(event.target !== this) return;' . $this->getOnChangeScript() . '});';
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
}