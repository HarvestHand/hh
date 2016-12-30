<?php

/**
 * Test class for HH_Tools_Countries.
 * Generated by PHPUnit on 2010-09-18 at 22:48:08.
 */
class HH_Tools_CountriesTest extends PHPUnit_Framework_TestCase
{

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {

    }

    public function testGetRawSubdivisions()
    {
        $json = HH_Tools_Countries::getRawSubdivisions('CA');
        $this->assertNotEmpty($json);
        $array = json_decode($json, true);
        $this->assertInternalType('array', $array);
        $this->assertArrayHasKey('NS', $array);
    }

    public function testGetSubdivisions()
    {
        $array = HH_Tools_Countries::getSubdivisions('CA');
        $this->assertInternalType('array', $array);
        $this->assertArrayHasKey('NS', $array);
    }

    public function testGetRawUnlocodes()
    {
        $json = HH_Tools_Countries::getRawUnlocodes('CA', 'NS');
        $this->assertNotEmpty($json);
        $array = json_decode($json, true);
        $this->assertInternalType('array', $array);
        $this->assertTrue(in_array('Kentville', $array));

        $json = HH_Tools_Countries::getRawUnlocodes('WS', 'LA');
        $this->assertNotEmpty($json);
        $array = json_decode($json, true);
        $this->assertInternalType('array', $array);
        $this->assertTrue(in_array('Lalomalava', $array));
    }

    public function testGetUnlocodes()
    {
        $array = HH_Tools_Countries::getUnlocodes('CA', 'NS');
        $this->assertInternalType('array', $array);
        $this->assertTrue(in_array('Kentville', $array));
    }

    public function testGetTimezone()
    {
        $timezone = HH_Tools_Countries::getTimezone('CA', 'NS');
        $this->assertEquals('America/Halifax', $timezone);
    }

}