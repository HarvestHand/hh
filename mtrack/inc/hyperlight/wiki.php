<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class WikiLanguage extends HyperLanguage {
  public function __construct() {
    $this->setInfo(array(
          parent::NAME => 'Wiki',
          ));
    $this->setExtensions(array('wiki'));
    $this->setCaseInsensitive(false);
    $this->addStates(array(
          'init' => array(
            'bold',
            'macro',
            'link',
            'replink',
            'keyword' => array('operator'),
            ),
          ));

    $this->addRules(array(
          'bold' => "/'''(?:\\\\'|.)*?'''/s",
          'macro' => "/\[\[.*\]\]/s",
          'link' => "/\[[a-z]+:.*\]/Us",
          'replink' => "/\{[^}]+\}/Us",
          'keyword' => array(
            'operator' => '/=+/',
            ),
          ));
        $this->addMappings(array(
            'bold' => 'string',
            'link' => 'tag',
            'replink' => 'tag',
            'macro' => 'tag',
        ));

  }
}
