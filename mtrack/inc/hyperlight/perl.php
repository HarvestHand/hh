<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class PerlLanguage extends HyperLanguage {
  public function __construct() {
    $this->setInfo(array(
          parent::NAME => 'Perl',
          ));
    $this->setExtensions(array('pl'));
    $this->setCaseInsensitive(false);
    $this->addStates(array(
          'init' => array(
            'string',
            'number',
            'char',
            'ticked',
            'variable',
            'comment',
            'keyword' => array('', 'operator'),
            'identifier'
            ),
          'variable' => array('identifier'),
          )
    );

    $this->addRules(array(
          'string' => Rule::C_DOUBLEQUOTESTRING,
          'char' => Rule::C_SINGLEQUOTESTRING,
          'ticked' => "/\`(?:\\\`|.)*\`/sU",
          'number' => Rule::C_NUMBER,
          'comment' => '/#.*/',
          'keyword' => array(
            array(
              'use', 'my', 'our', 'open', 'close', 'tie',
              'exists', 'keys', 'values', 'chomp',
              'last', 'next', 'print', 'unless',
              'and', 'or', 'not', 'defined', 'undef',
              'push', 'unshift', 'shift', 'pop',
              'system', 'exec', 'goto', 'uc', 'lc',
              'length', 'split',
              'sort', 'grep', 'map', 'die', 'eval',
              'require', 'bless', 'sub', 'package',
              'eq', 'ne', 'le', 'lt', 'ge', 'gt',
              'else', 'for', 'foreach', 'then',
              'if', 'in', 'case', 'esac', 'while',
              'end', 'do', 'return', 'elsif', 'exit'
              ),
            'operator' => '/&&|\|\||<<|>>|\.=|==|=~|!~|[=;&|!<>\[\].]/',
            ),
          'identifier' => Rule::C_IDENTIFIER,
          'variable' => new Rule('/(@\$|%\$|&\$|@|%|&|\$)/', '//'),
          ));
        $this->addMappings(array(
            'char' => 'string',
            'variable' => 'tag',
            'ticked' => 'string',
        ));

  }
}
