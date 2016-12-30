<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class ShellLanguage extends HyperLanguage {
  public function __construct() {
    $this->setInfo(array(
          parent::NAME => 'Shell',
          ));
    $this->setExtensions(array('sh'));
    $this->setCaseInsensitive(false);
    $this->addStates(array(
          'init' => array(
            'string',
            'char',
            'ticked',
            'comment',
            'keyword' => array('', 'operator'),
            'identifier'
            ),
          ));

    $this->addRules(array(
          'string' => Rule::C_DOUBLEQUOTESTRING,
          'char' => Rule::C_SINGLEQUOTESTRING,
          'ticked' => "/\`(?:\\\`|.)*\`/sU",
          'comment' => '/#.*/',
          'keyword' => array(
            array(
              'break', 'test', 'continue',
              'else', 'for', 'then',
              'if', 'in', 'case', 'esac', 'while',
              'end', 'fi', 'until', 'return', 'elif', 'exit'
              ),
            'operator' => '/[;&|!<>\[\]]|&&|\$\(\(|\$\(|\)\)|\)|\(\|\||<<|>>|=|==/',
            ),
          'identifier' => Rule::C_IDENTIFIER,
          ));
        $this->addMappings(array(
            'char' => 'string',
            'ticked' => 'string',
        ));

  }
}
