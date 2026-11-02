<?php
namespace exface\JEasyUIFacade\Facades\Elements;

/**
 * This InputCode facade uses the Ace editor alongside a set of formatters in order to display code.
 * 
 * @method \exface\Core\Widgets\InputCode getWidget()
 * 
 * @author Sergej Riel
 */
class EuiInputCode extends EuiInput
{
    protected function init()
    {
        parent::init();
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildHtmlHeadTags()
     */
    public function buildHtmlHeadTags()
    {
        $widget = $this->getWidget();
        $codeFormatterLanguage = $widget->getCodeFormatter()->getLanguage();
        
        $includes = parent::buildHtmlHeadTags();
        $facade = $this->getFacade();
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.INPUT_CODE.ACE.JS') . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.INPUT_CODE.ACE.THEME') . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.INPUT_CODE.SQL.FORMATTER') . '"></script>';
        
        if ($codeFormatterLanguage === 'javascript') {
            // the prettier formatter needs different plugins for different languages.
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.INPUT_CODE.PRETTIER') . '"></script>';
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.INPUT_CODE.PRETTIER.PLUGINS', false) . '/babel.js' . '"></script>';
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.INPUT_CODE.PRETTIER.PLUGINS', false) . '/estree.js' . '"></script>';
        }
        
        return $includes;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiValue::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        return parent::buildCssElementClass() . ' exf-inputcode';
    }
    
    public function buildHtml()
    {
        $widget = $this->getWidget();
        $editorWidth = $widget->getWidth()->getValue() ?? '100%';
        $editorHeight = $widget->getHeight()->getValue() ?? '100%';
        
        return <<<HTML
        <div id="{$this->getIdOfAce()}" style="width: $editorWidth ; height: $editorHeight"></div>
HTML;
    }
    
    protected function getIdOfAce() : string
    {
        return $this->getId() . '_ace';
    }

    public function buildJs()
    {
        $js = parent::buildJs();
        
        $widget = $this->getWidget();
        $aceEditorLanguage = $widget->getCodeFormatter()->getColorize()
          ? mb_strtolower($widget->getCodeFormatter()->getLanguage() ?? $widget->getLanguage() ?? 'text')
          : 'text';
        $setModeJs = '';
        if (in_array($aceEditorLanguage, $this->getAceLanguages())) {
            $setModeJs = "
                editor.session.setMode('ace/mode/$aceEditorLanguage');";
        }
        if ($widget->hasWrapLines()) {
            $setModeJs .= "
                editor.session.setUseWrapMode(true);";
        }
        
        $js .= <<<JS
          
            (function(inputValue){
                const editor = ace.edit('{$this->getIdOfAce()}', {
                    theme: 'ace/theme/crimson_editor',
                    readOnly: {$this->escapeBool($widget->isDisabled())},
                });
                {$setModeJs}
                
                {$this->buildJsFormatCode('inputValue')}.then(function (formattedInput) {
                    editor.setValue(formattedInput, -1);
                })
            })({$this->escapeString($widget->getValueWithDefaults(), true, false)});          
JS;
        
        return $js;
    }

    /**
     * Takes input string and gives formatted string back
     * It returns the input string if formatting fails or is disabled.
     * 
     * @param string $input
     * @return string
     */
    protected function buildJsFormatCode(string $input) : string
    {
        $widget = $this->getWidget();
        $codeFormatterLanguage = $widget->getCodeFormatter()->getLanguage();
        $codeFormatterDialect = $widget->getCodeFormatter()->getDialect();
        $prettifyCode = json_encode($widget->getCodeFormatter()->getPrettify());
        
        return <<<JS
      
      (async function(input){
        if (!$prettifyCode) return input;
        
        const language = '{$codeFormatterLanguage}';
        const dialect = '{$codeFormatterDialect}';

        const FORMATTERS = {
          sql: (code, dialect) => {
            const language = dialect || 'sql';
            return sqlFormatter.format(code, {language});
          },
          javascript: async function (code) {
            return await prettier.format(code, {
              parser: "babel",
              plugins: prettierPlugins,
            });
          },
          json: (code) => {
              const obj = JSON.parse(code);
              return JSON.stringify(obj, null, 2);
          },

        };
        
          const formatter = FORMATTERS[language];
          
          if (!formatter) return input;
        
          try { 
            return await formatter(input, dialect);
          } catch (e) {
            console.warn(
              "Code could not be formatted (language: " + language + ", dialect: " + dialect +")",
              e
            );
            return input;
          }
        
      })($input)
JS;

    }

    /**
     * Returns all supported languages
     * 
     * @return string[]
     */
    protected function getAceLanguages() : array
    {
        return ['abap','abc','actionscript','ada','alda','apache_conf','apex','applescript','aql','asciidoc','asl','assembly_arm32','assembly_x86','astro','autohotkey','basic','batchfile','bibtex','c_cpp','c9search','cirru','clojure','cobol','coffee','coldfusion','crystal','csharp','csound_document','csound_orchestra','csound_score','csp','css','curly','cuttlefish','d','dart','diff','django','dockerfile','dot','drools','edifact','eiffel','ejs','elixir','elm','erlang','flix','forth','fortran','fsharp','fsl','ftl','gcode','gherkin','gitignore','glsl','gobstones','golang','graphqlschema','groovy','haml','handlebars','haskell','haskell_cabal','haxe','hjson','html','html_elixir','html_ruby','ini','io','ion','jack','jade','java','javascript','jexl','json','json5','jsoniq','jsp','jssm','jsx','julia','kotlin','latex','latte','less','liquid','lisp','livescript','logiql','logtalk','lsl','lua','luapage','lucene','makefile','markdown','mask','matlab','maze','mediawiki','mel','mips','mixal','mushcode','mysql','nasal','nginx','nim','nix','nsis','nunjucks','objectivec','ocaml','odin','partiql','pascal','perl','pgsql','php','php_laravel_blade','pig','plain_text','plsql','powershell','praat','prisma','prolog','properties','protobuf','prql','puppet','python','qml','r','raku','razor','rdoc','red','redshift','rhtml','robot','rst','ruby','rust','sac','sass','scad','scala','scheme','scrypt','scss','sh','sjs','slim','smarty','smithy','snippets','soy_template','space','sparql','sql','sqlserver','stylus','svg','swift','tcl','terraform','tex','text','textile','toml','tsx','turtle','twig','typescript','vala','vbscript','velocity','verilog','vhdl','visualforce','vue','wollok','xml','xquery','yaml','zeek','zig'];
    }
}