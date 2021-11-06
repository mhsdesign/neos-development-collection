<?php
namespace Neos\Fusion\Tests\Unit\Core\Parser;

/*
 * This file is part of the Neos.Fusion package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Neos\Flow\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use Neos\Fusion\Core\FilePatternResolver;

/**
 * Testcase for the FilePattern for the Fusion Parser
 */
class FilePatternTest extends UnitTestCase
{
    protected $file_system;

    public function setUp(): void
    {
        $directory = [
            'Simple' => [
                'file.fusion' => '',
                'root.fusion' => '',
            ],
            'Globbing' => [
                'Nested' => [
                    'a.fusion' => '',
                    'b.js' => '',
                    'Deep' => [
                        'abc-de.fusion' => '',
                        'main.js' => '',
                    ]
                ],
                'javascript.js' => '',
                'style.css' => '',
                'b.fusion' => '',
                'abc-de.fusion' => '',
                'äfw-öü.fusion' => '',

            ],
            'Fusion' => [
                'Root.fusion' => '',
            ],
            'some.fusion' => '',
            'Readme.md' => '',
        ];
        $this->file_system = vfsStream::setup('root', null, $directory);
    }

    /**
     * @param array|string $paths with leading /
     * @return string|string[]
     */
    protected function addStreamWrapper($paths)
    {
        if (is_string($paths)) {
            return $this->file_system->url() . $paths;
        }
        if (is_array($paths)) {
            return array_map(function ($path) {
                return $this->file_system->url() . $path;
            }, $paths);
        }
        throw new \LogicException('expected string|array');
    }

    /**
     * @test
     */
    public function testSimpleFileInclude()
    {
        $pattern = $this->addStreamWrapper('/Simple/file.fusion');
        $filePathForRelativeResolves = null;
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);
        $expected = ['/Simple/file.fusion'];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testSimpleFileIncludeWithSpace()
    {
        $pattern = '      ' . $this->addStreamWrapper('/Simple/file.fusion') . '      ';
        $filePathForRelativeResolves = null;
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);
        $expected = ['/Simple/file.fusion'];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testSimpleRelativeFileInclude()
    {
        $pattern = 'file.fusion';
        $filePathForRelativeResolves = $this->addStreamWrapper('/Simple/root.fusion');
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);
        $expected = ['/Simple/file.fusion'];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testRelativeFileIncludeWithoutCurrentFilePath()
    {
        self::expectException(\Exception::class);
        $pattern = 'file.fusion';
        $filePathForRelativeResolves = null;
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);
        $expected = ['/Simple/file.fusion'];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testSimpleGlobbing()
    {
        $pattern = $this->addStreamWrapper('/Globbing/*.fusion');
        $filePathForRelativeResolves = null;
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);

        $expected = [
            '/Globbing/b.fusion',
            '/Globbing/abc-de.fusion',
            '/Globbing/äfw-öü.fusion'
        ];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testSimpleRelativeGlobbing()
    {
        $pattern = './*.fusion';
        $filePathForRelativeResolves = $this->addStreamWrapper('/Globbing/b.fusion');
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);

        $expected = [
            '/Globbing/./b.fusion',
            '/Globbing/./abc-de.fusion',
            '/Globbing/./äfw-öü.fusion'
        ];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testSimpleRelativeMinimalWithExplicitExtensionGlobbing()
    {
        $pattern = '*.fusion';
        $filePathForRelativeResolves = $this->addStreamWrapper('/Globbing/b.fusion');
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);

        $expected = [
            '/Globbing/b.fusion',
            '/Globbing/abc-de.fusion',
            '/Globbing/äfw-öü.fusion'
        ];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testSimpleRelativeMinimalGlobbing()
    {
        $pattern = '*';
        $filePathForRelativeResolves = $this->addStreamWrapper('/Globbing/b.fusion');
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);

        $expected = [
            '/Globbing/b.fusion',
            '/Globbing/abc-de.fusion',
            '/Globbing/äfw-öü.fusion'
        ];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testRecursiveGlobbing()
    {
        $pattern = $this->addStreamWrapper('/Globbing/**/*');
        $filePathForRelativeResolves = null;
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);

        $expected = [
            '/Globbing/Nested/a.fusion',
            '/Globbing/Nested/Deep/abc-de.fusion',
            '/Globbing/b.fusion',
            '/Globbing/abc-de.fusion',
            '/Globbing/äfw-öü.fusion'
        ];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testRelativeRecursiveGlobbing()
    {
        $pattern = 'Globbing/**/*.fusion';
        $filePathForRelativeResolves = $this->addStreamWrapper('/some.fusion');
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);

        $expected = [
            '/Globbing/Nested/a.fusion',
            '/Globbing/Nested/Deep/abc-de.fusion',
            '/Globbing/b.fusion',
            '/Globbing/abc-de.fusion',
            '/Globbing/äfw-öü.fusion'
        ];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testMinimalSyntaxRelativeRecursiveGlobbing()
    {
        $pattern = '**/*';
        $filePathForRelativeResolves = $this->addStreamWrapper('/Globbing/b.fusion');
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);

        $expected = [
            '/Globbing/Nested/a.fusion',
            '/Globbing/Nested/Deep/abc-de.fusion',
            '/Globbing/b.fusion',
            '/Globbing/abc-de.fusion',
            '/Globbing/äfw-öü.fusion'
        ];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testRelativeAlternateSyntaxRecursiveGlobbing()
    {
        $pattern = './**/*.fusion';
        $filePathForRelativeResolves = $this->addStreamWrapper('/Globbing/b.fusion');
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);

        $expected = [
            '/Globbing/./Nested/a.fusion',
            '/Globbing/./Nested/Deep/abc-de.fusion',
            '/Globbing/./b.fusion', # Need to be filtered out otherwise would be recursive.
            '/Globbing/./abc-de.fusion',
            '/Globbing/./äfw-öü.fusion'
        ];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testRelativeRecursiveGlobbingFromNested()
    {
        $pattern = '../../**/*';
        $filePathForRelativeResolves = $this->addStreamWrapper('/Globbing/Nested/Deep/abc-de.fusion');
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);

        $expected = [
            '/Globbing/Nested/Deep/../../Nested/a.fusion',
            '/Globbing/Nested/Deep/../../Nested/Deep/abc-de.fusion', # Need to be filtered out otherwise would be recursive.
            '/Globbing/Nested/Deep/../../b.fusion',
            '/Globbing/Nested/Deep/../../abc-de.fusion',
            '/Globbing/Nested/Deep/../../äfw-öü.fusion'
        ];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testRecursiveGlobbingWithSpecialFileEndPath()
    {
        $pattern = $this->addStreamWrapper('/Globbing/**/*-de.fusion');
        $filePathForRelativeResolves = null;
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);

        $expected = [
            '/Globbing/Nested/Deep/abc-de.fusion',
            '/Globbing/abc-de.fusion',
        ];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * @test
     */
    public function testRecursiveGlobbingWithDifferentExtension()
    {
        $pattern = $this->addStreamWrapper('/Globbing/**/*.js');
        $filePathForRelativeResolves = null;
        $files = FilePatternResolver::resolveFilesByPattern($pattern, $filePathForRelativeResolves);

        $expected = [
            '/Globbing/Nested/b.js',
            '/Globbing/Nested/Deep/main.js',
            '/Globbing/javascript.js',
        ];
        self::assertEquals($this->addStreamWrapper($expected), $files);
    }

    /**
     * FilePattern accept only simple File paths or /**\/* and /*
     */
    public function unsupportedGlobbingTechnics(): array
    {
        return [
            'simple glob at end without slash (that means its a file)' => ['/file*'],
            'simple glob inside filename' => ['/file*name.fusion'],
            'recursive glob at end without slash' => ['/folder**/*'],
            'simple glob with superfluous star' => ['/folder/**'],
            'recursive glob with superfluous star' => ['/folder/**/**'],
            'recursive glob with specific filename' => ['/folder/**/filename.fusion'],
            'recursive glob with specific recursion folder' => ['/folder/*folder*/*'],
            'recursive glob with normal folder glob' => ['/folder/**/*/'],
            'recursive glob with normal folder glob and filename' => ['/folder/**/*/file.fusion'],
            'recursive glob with specific folder' => ['/folder/**/*folder/file.fusion'],
            'multiple globing mixed' => ['/folder/*/folder/**/*'],
            'simple glob only for folder' => ['/folder/*/file.fusion'],
            'recursive glob with glob filename' => ['/folder/**/*file*.fusion'],
        ];
    }

    /**
     * @test
     * @dataProvider unsupportedGlobbingTechnics
     */
    public function testUnsupportedGlobbingTechnic($pattern)
    {
        $pattern = $this->addStreamWrapper($pattern);
        self::expectException(\Exception::class);
        self::expectExceptionCode(1636144713);
        FilePatternResolver::resolveFilesByPattern($pattern);
    }
}
