<?php

use PHPUnit\Framework\TestCase;

final class DefaultTest extends TestCase
{
    public function tearDown()
    {
        @unlink("/tmp/xy.z");
        @unlink("/tmp/la.la");
        @unlink("/tmp/lala.js");
        @unlink("/tmp/lala.min.js");
        @unlink("/tmp/lala.css");
        @unlink("/tmp/lala.min.css");
        @unlink("/tmp/lala2.css");
        @unlink("/tmp/lala.cmb.css");
    }

    public function testVersion()
    {
        $this->assertSame("1.3.0", Mince::getVersion());
    }

    public function testConstruct()
    {
        $mince = new Mince();
        $mince->setConfigFile("xy.z");
        $this->assertTrue(true);
    }

    public function testConfig()
    {
        $data = "";
        file_put_contents("/tmp/xy.z", $data);
        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $this->assertSame([], $mince->getConfig());
    }

    public function testExecute()
    {
        $mince = new Mince();
        $mince->setConfigFile("xy.z");
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Minceconf file not found");
        $mince->execute();
    }

    public function testReadConfig()
    {
        $data = "";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No minify or combine directives in minceconf.");
        $mince->execute();
    }

    public function testCountRulesEmpty()
    {
        $data = "minify:";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No minify or combine directives in minceconf.");
        $mince->execute();
    }

    public function testCountRulesEmptyArray()
    {
        $data = "minify:\n  -";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $mince->execute();
        $this->assertSame("Parsed 1 rules.", $mince->output[2]);
    }

    public function testExecuteMinifyEmpty()
    {
        $data = "minify:\ncombine:\n  -";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $mince->execute();
        $this->assertSame("Parsed 0 rules.", $mince->output[2]);
    }

    public function testExecuteCombineEmpty()
    {
        $data = "minify:\n  -\ncombine:\n";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $mince->execute();
        $this->assertSame("Parsed 1 rules.", $mince->output[2]);
    }

    public function testExecuteAdditionalKeyNoop()
    {
        $data = "minify:\n  -\ncombine:\nfoo:\n";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $mince->execute();
        $this->assertSame("Parsed 1 rules.", $mince->output[2]);
    }

    public function testCountRulesMinifyFileNotExist()
    {
        $data = "minify:\n - ab.c";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $mince->execute();
        $this->assertSame("WARNING: File 'ab.c' doesn't exist.", $mince->output[4]);
    }

    public function testCountRulesMinifySinglefile()
    {
        $data = "minify: la.la";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $mince->execute();
        $this->assertSame("WARNING: File 'la.la' doesn't exist.", $mince->output[4]);
    }

    public function testCountRulesMinifySinglefileExists()
    {
        file_put_contents("/tmp/la.la", ".ab {\n  border: 1px solid red;\n}\n");

        $data = "minify: /tmp/la.la";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $mince->execute();
        $this->assertSame("Parsed 1 rules.", $mince->output[2]);
    }

    public function testMinifyCssfile()
    {
        file_put_contents("/tmp/lala.css", ".ab {\n  border: 1px solid red;\n}\n");

        $data = "minify:\n  - /tmp/lala.css";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $mince->execute();
        $this->assertContains("csstidy", $mince->output[4]);

        $contents = file_get_contents("/tmp/lala.min.css");
        $this->assertSame(".ab{border:1px solid red;}", $contents);
    }

    public function testMinifyJsfile()
    {
        file_put_contents("/tmp/lala.js", "var Xy = {\n  points: 900\n};");

        $data = "minify:\n  - /tmp/lala.js";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $mince->execute();
        $this->assertContains("jsmin", $mince->output[4]);

        $contents = file_get_contents("/tmp/lala.min.js");
        $this->assertSame("\nvar Xy={points:900};", $contents);
    }

    public function testMinifyWithBlank()
    {
        file_put_contents("/tmp/lala.css", ".ab {\n  border: 1px solid red;\n}\n");
        file_put_contents("/tmp/lala.js", "var Xy = {\n  points: 900\n};");

        $data = "minify:\n  - /tmp/lala.js\n  -\n  - /tmp/lala.css";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $mince->execute();
        $this->assertContains("jsmin", $mince->output[4]);

        $contents = file_get_contents("/tmp/lala.min.js");
        $this->assertSame("\nvar Xy={points:900};", $contents);
    }

    public function testCombineCountJustString()
    {
        $data = "combine: /tmp/lala.cmb";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->readConfig("/tmp/xy.z");
        $this->assertSame("Parsed 0 rules.", $mince->output[2]);
    }

    public function testCombineCountWithEmpty()
    {
        $data = "---\ncombine:\n  /tmp/lala.cmb:\n    -";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->readConfig("/tmp/xy.z");
        $this->assertSame("Parsed 0 rules.", $mince->output[2]);
    }

    public function testCombineCountNotEmpty()
    {
        $data = "combine:\n  /tmp/lala.cmb.css:\n    - /tmp/lala.min.css";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->readConfig("/tmp/xy.z");
        $this->assertSame("Parsed 1 rules.", $mince->output[2]);
    }

    public function testCombineWithFiles()
    {
        file_put_contents("/tmp/lala.css", ".ab {\n  border: 1px solid red;\n}\n");
        file_put_contents("/tmp/lala2.css", ".ax {\n border: 1px solid blue;\n}\n");

        $data = "combine:\n  /tmp/lala.cmb.css:\n    - /tmp/lala.css\n    - /tmp/lala2.css";
        file_put_contents("/tmp/xy.z", $data);

        $mince = new Mince();
        $mince->setConfigFile("/tmp/xy.z");

        $mince->execute();
        $this->assertSame("Adding /tmp/lala.css to /tmp/lala.cmb.css", $mince->output[4]);
        $this->assertSame("Adding /tmp/lala2.css to /tmp/lala.cmb.css", $mince->output[5]);

        $contents = file_get_contents("/tmp/lala.cmb.css");
        $this->assertContains(".ab {", $contents);
        $this->assertContains(".ax {", $contents);
    }
}
