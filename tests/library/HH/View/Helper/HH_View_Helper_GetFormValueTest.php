<?php

/**
 * Test class for HH_View_Helper_GetFormValue.
 * Generated by PHPUnit on 2010-09-19 at 20:17:41.
 */
class HH_View_Helper_GetFormValueTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var HH_View_Helper_GetFormValue
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new HH_View_Helper_GetFormValue;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {

    }

    /**
     * @todo Implement testGetFormValue().
     */
    public function testGetFormValue()
    {
        $_POST['test'] = 'test';
        $this->assertEquals('test', $this->object->getFormValue('test'));
        $_GET['testing'] = 'testing';
        $this->assertEquals('testing', $this->object->getFormValue('testing'));
        $_POST['testGroup'] = array('test' => 'test');
        $this->assertEquals('test', $this->object->getFormValue('test', 'testGroup'));
        $_GET['testingGroup'] = array('testing' => 'testing');
        $this->assertEquals('testing', $this->object->getFormValue('testing', 'testingGroup'));
        $this->assertNull($this->object->getFormValue('nothing'));
    }

}