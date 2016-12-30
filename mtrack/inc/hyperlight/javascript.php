<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class JavascriptLanguage extends HyperLanguage {
  public function __construct() {
    $this->setInfo(array(
          parent::NAME => 'Javascript',
          ));
    $this->setExtensions(array('js', 'json'));
    $this->setCaseInsensitive(false);
    $this->addStates(array(
          'init' => array(
            'string',
            'char',
            'number',
            'comment',
            'keyword' => array('', 'literal', 'operator'),
            'identifier'
            ),
          ));

    $this->addRules(array(
          'string' => Rule::C_DOUBLEQUOTESTRING,
          'char' => Rule::C_SINGLEQUOTESTRING,
          'number' => Rule::C_NUMBER,
          'comment' => Rule::C_COMMENT,
          'keyword' => array(
            array(
              'assert', 'break', 'class', 'continue',
              'else', 'except', 'finally', 'for',
              'if', 'in', 'function',
              'throw', 'return', 'try', 'while', 'with', 'typeof'
              ),
            'literal' => array(
              'false', 'null', 'true'
              ),
            'operator' => '/[-+*\/%&|^!~=<>?{}()\[\].,:;]|&&|\|\||<<|>>|[-=!<>+*\/%&|^]=|<<=|>>=|->/',
            ),
          'identifier' => Rule::C_IDENTIFIER,
          ));
        $this->addMappings(array(
            'char' => 'string',
        ));

  }
}
