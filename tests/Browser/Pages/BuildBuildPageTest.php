<?php

namespace Tests\Browser\Pages;

use App\Models\Build;
use App\Models\BuildError;
use App\Models\BuildGroup;
use App\Models\BuildUpdate;
use App\Models\Label;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\SubProject;
use App\Services\SiteService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class BuildBuildPageTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;

    private Site $site;

    private SubProject $subproject1;
    private SubProject $subproject2;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $buildgroup1 = BuildGroup::factory()->for($this->project)->create();
        $buildgroup2 = BuildGroup::factory()->for($this->project)->create();

        $this->subproject1 = SubProject::create([
            'name' => Str::uuid()->toString(),
            'projectid' => $this->project->id,
            'groupid' => $buildgroup1->id,
        ]);

        $this->subproject2 = SubProject::create([
            'name' => Str::uuid()->toString(),
            'projectid' => $this->project->id,
            'groupid' => $buildgroup2->id,
        ]);

        $this->site = Site::factory()->create();
        SiteService::updateSiteInfoIfChanged($this->site, new SiteInformation([]));
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();

        parent::tearDown();
    }

    public function testShowsBuildName(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText($build->name)
                ->assertSee($build->name)
            ;
        });
    }

    /**
     * @return array<array<string>>
     */
    public static function showsBuildInfoFieldIfSetCases(): array
    {
        return [
            ['@compiler-name', 'compilername'],
            ['@compiler-version', 'compilerversion'],
            ['@generator', 'generator'],
            ['@source-directory', 'sourcedirectory'],
            ['@binary-directory', 'binarydirectory'],
            ['@command', 'command'],
        ];
    }

    #[DataProvider('showsBuildInfoFieldIfSetCases')]
    public function testShowsBuildInfoFieldIfSet(string $testid, string $attribute): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($attribute, $testid, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitFor('@build-info')
                ->assertMissing($testid)
            ;

            $build->setAttribute($attribute, Str::uuid()->toString());
            $build->save();

            $browser->visit("/builds/{$build->id}/build")
                ->waitFor('@build-info')
                ->assertVisible($testid)
                ->assertSeeIn($testid, $build->getAttribute($attribute))
            ;
        });
    }

    public function testShowsMessageWhenNoErrorsOrWarnings(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('No errors or warnings for this build.')
                ->assertSee('No errors or warnings for this build.')
            ;
        });
    }

    public function testDoesntShowMessageWhenErrorsPresent(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $build->buildErrors()->save(BuildError::factory()->make());
        $build->buildErrors()->save(BuildError::factory()->make());

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('2 ERRORS')
                ->assertDontSee('No errors or warnings for this build.')
            ;
        });
    }

    public function testShowsMessageWhenNoNewErrorsOrWarnings(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/build?onlydeltap")
                ->waitForText('No new errors or warnings for this build.')
                ->assertSee('No new errors or warnings for this build.')
            ;
        });
    }

    public function testShowsMessageWhenNoFixedErrorsOrWarnings(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/build?onlydeltan")
                ->waitForText('No fixed errors or warnings for this build.')
                ->assertSee('No fixed errors or warnings for this build.')
            ;
        });
    }

    public function testShowsErrorsForSingleBuildWithSingleError(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make());

        $this->browse(function (Browser $browser) use ($buildError1, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 ERROR')
                ->assertSee($buildError1->stdoutput)
                ->assertSee($buildError1->stderror)
            ;
        });
    }

    public function testShowsErrorsForSingleBuildWithMultipleErrors(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make());
        /** @var BuildError $buildError2 */
        $buildError2 = $build->buildErrors()->save(BuildError::factory()->make());

        $this->browse(function (Browser $browser) use ($buildError2, $buildError1, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('2 ERRORS')
                ->assertSee($buildError1->stdoutput)
                ->assertSee($buildError1->stderror)
                ->assertSee($buildError2->stdoutput)
                ->assertSee($buildError2->stderror)
            ;
        });
    }

    public function testShowsWarningsForSingleBuildWithSingleWarning(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make([
            'type' => 1,
        ]));

        $this->browse(function (Browser $browser) use ($buildError1, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 WARNING')
                ->assertSee($buildError1->stdoutput)
                ->assertSee($buildError1->stderror)
            ;
        });
    }

    public function testShowsWarningsForSingleBuildWithMultipleWarnings(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make([
            'type' => 1,
        ]));
        /** @var BuildError $buildError2 */
        $buildError2 = $build->buildErrors()->save(BuildError::factory()->make([
            'type' => 1,
        ]));

        $this->browse(function (Browser $browser) use ($buildError2, $buildError1, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('2 WARNINGS')
                ->assertSee($buildError1->stdoutput)
                ->assertSee($buildError1->stderror)
                ->assertSee($buildError2->stdoutput)
                ->assertSee($buildError2->stderror)
            ;
        });
    }

    public function testCollapsesLongStdout(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make([
            'type' => 1,
        ]));

        $this->browse(function (Browser $browser) use ($buildError1, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 WARNING')
                ->assertSee($buildError1->stdoutput)
            ;

            $buildError1->stdoutput .= "a\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na";
            $buildError1->save();

            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 WARNING')
                ->assertDontSee($buildError1->stdoutput)
                ->click('@stdout')
                ->assertSee($buildError1->stdoutput)
            ;
        });
    }

    public function testLinksToMainBranchWhenRepositoryConfiguredAndNoUpdateStep(): void
    {
        $this->project->cvsviewertype = 'github';
        $this->project->cvsurl = 'https://example.com/org/repo';
        $this->project->save();

        $stdOutPart1 = Str::uuid()->toString();
        $stdOutPart2 = Str::uuid()->toString();
        $sourceDirectory = '/absolute/path/to/source';
        $filePath = $sourceDirectory . '/foo/bar.cpp:10:20';

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'sourcedirectory' => $sourceDirectory,
        ]);

        $build->buildErrors()->save(BuildError::factory()->make([
            'stdoutput' => $stdOutPart1 . $filePath . $stdOutPart2,
        ]));

        $this->browse(function (Browser $browser) use ($filePath, $stdOutPart2, $stdOutPart1, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 ERROR')
                ->assertSee($stdOutPart1)
                ->assertSee($stdOutPart2)
                ->assertSeeLink($filePath)
                ->assertPresent('a[href="https://example.com/org/repo/blob/main/foo/bar.cpp"]')
            ;
        });
    }

    public function testLinksCorrectlyWhenNotRootOfRepository(): void
    {
        $this->project->cvsviewertype = 'github';
        $this->project->cvsurl = 'https://example.com/org/repo';
        $this->project->cmakeprojectroot = '/source';
        $this->project->save();

        $stdOutPart1 = Str::uuid()->toString();
        $stdOutPart2 = Str::uuid()->toString();
        $sourceDirectory = '/absolute/path/to/source';
        $filePath = $sourceDirectory . '/foo/bar.cpp:10:20';

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'sourcedirectory' => $sourceDirectory,
        ]);

        $build->buildErrors()->save(BuildError::factory()->make([
            'stdoutput' => $stdOutPart1 . $filePath . $stdOutPart2,
        ]));

        $this->browse(function (Browser $browser) use ($filePath, $stdOutPart2, $stdOutPart1, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 ERROR')
                ->assertSee($stdOutPart1)
                ->assertSee($stdOutPart2)
                ->assertSeeLink($filePath)
                ->assertPresent('a[href="https://example.com/org/repo/blob/main/source/foo/bar.cpp"]')
            ;
        });
    }

    public function testLinksToUpdateRevisionWhenRepositoryConfiguredAndHasUpdateStep(): void
    {
        $this->project->cvsviewertype = 'github';
        $this->project->cvsurl = 'https://example.com/org/repo';
        $this->project->save();

        $stdOutPart1 = Str::uuid()->toString();
        $stdOutPart2 = Str::uuid()->toString();
        $sourceDirectory = '/absolute/path/to/source';
        $filePath = $sourceDirectory . '/foo/bar.cpp:10:20';

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'sourcedirectory' => $sourceDirectory,
        ]);

        $build->buildErrors()->save(BuildError::factory()->make([
            'stdoutput' => $stdOutPart1 . $filePath . $stdOutPart2,
        ]));

        /** @var BuildUpdate $update */
        $update = BuildUpdate::create([
            'command' => Str::uuid()->toString(),
            'type' => 'GIT',
            'status' => Str::uuid()->toString(),
            'revision' => Str::uuid()->toString(),
            'priorrevision' => Str::uuid()->toString(),
            'path' => Str::uuid()->toString(),
        ]);
        $build->updateStep()->associate($update)->save();

        $this->browse(function (Browser $browser) use ($update, $filePath, $stdOutPart2, $stdOutPart1, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 ERROR')
                ->assertSee($stdOutPart1)
                ->assertSee($stdOutPart2)
                ->assertSeeLink($filePath)
                ->assertPresent('a[href="https://example.com/org/repo/blob/' . $update->revision . '/foo/bar.cpp"]')
            ;
        });
    }

    public function testLinkifiesCorrectlyWhenNoSourceDirectoryProvided(): void
    {
        $this->project->cvsviewertype = 'github';
        $this->project->cvsurl = 'https://example.com/org/repo';
        $this->project->save();

        $stdOutPart1 = Str::uuid()->toString();
        $stdOutPart2 = Str::uuid()->toString();
        $filePath = '/.../repository/foo/bar.cpp:10:20';

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $build->buildErrors()->save(BuildError::factory()->make([
            'stdoutput' => $stdOutPart1 . $filePath . ':' . $stdOutPart2,
        ]));

        $this->browse(function (Browser $browser) use ($filePath, $stdOutPart2, $stdOutPart1, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 ERROR')
                ->assertSee($stdOutPart1)
                ->assertSee($stdOutPart2)
                ->assertSeeLink($filePath)
                ->assertPresent('a[href="https://example.com/org/repo/blob/main/foo/bar.cpp"]')
            ;
        });
    }

    public function testLinksKeepAnsiStylesExceptColors(): void
    {
        $this->project->cvsviewertype = 'github';
        $this->project->cvsurl = 'https://example.com/org/repo';
        $this->project->save();

        $stdOutPart = Str::uuid()->toString();
        $sourceDirectory = '/absolute/path/to/source';
        $filePath = $sourceDirectory . '/foo/bar.cpp:10:20';

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'sourcedirectory' => $sourceDirectory,
        ]);

        // GCC-style output with a bold, cyan on red path, with the escape characters encoded the way CTest submits them.
        $build->buildErrors()->save(BuildError::factory()->make([
            'stdoutput' => '[NON-XML-CHAR-0x1B][01;36;41m[NON-XML-CHAR-0x1B][K' . $filePath . ':[NON-XML-CHAR-0x1B][m[NON-XML-CHAR-0x1B][K ' . $stdOutPart,
        ]));

        $this->browse(function (Browser $browser) use ($filePath, $stdOutPart, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 ERROR')
                ->assertSee($stdOutPart)
                ->assertSeeLink($filePath)
                ->assertPresent('a[href="https://example.com/org/repo/blob/main/foo/bar.cpp"]')
            ;

            [$linkStyle, $pathStyle, $afterStyle] = $browser->script(
                'const link = document.querySelector(\'[data-test="stdout"] + pre a\');'
                . 'return [link, link.firstElementChild, link.nextElementSibling.firstElementChild].map((element) => {'
                . '  const style = getComputedStyle(element);'
                . '  return [style.fontWeight, style.color, style.backgroundColor];'
                . '});'
            )[0];

            // The link and the text after it stay bold, but only the text after the link keeps the output's colors.
            self::assertSame(['700', $linkStyle[1], $linkStyle[2]], $pathStyle);
            self::assertSame(['700', 'rgb(0, 187, 187)', 'rgb(187, 0, 0)'], $afterStyle);
        });
    }

    public function testLinksPathsContainingHtmlWithoutRenderingIt(): void
    {
        $this->project->cvsviewertype = 'github';
        $this->project->cvsurl = 'https://example.com/org/repo';
        $this->project->save();

        $html = '<img/src/onerror=window.xss=1>';
        $sourceDirectory = '/absolute/path/to/source';
        $filePath = $sourceDirectory . '/R&D/<img/src/onerror=window.xss=2>.cpp:10:20';

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'sourcedirectory' => $sourceDirectory,
        ]);

        $build->buildErrors()->save(BuildError::factory()->make([
            'stdoutput' => $html . '[NON-XML-CHAR-0x1B][01m' . $filePath . ':[NON-XML-CHAR-0x1B][m ' . $html,
        ]));

        $this->browse(function (Browser $browser) use ($html, $filePath, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 ERROR')
                ->assertSee($html)
                ->assertSeeLink($filePath)
                ->assertPresent('a[href="https://example.com/org/repo/blob/main/R%26D/%3Cimg/src/onerror%3Dwindow.xss%3D2%3E.cpp"]')
                ->assertMissing('pre img')
            ;
            self::assertNull($browser->script('return window.xss;')[0]);
        });
    }

    public function testLinksOnlyPathsWhenSourceDirectoryAppearsEarlier(): void
    {
        $this->project->cvsviewertype = 'github';
        $this->project->cvsurl = 'https://example.com/org/repo';
        $this->project->save();

        $sourceDirectory = '/absolute/path/to/source';

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'sourcedirectory' => $sourceDirectory,
        ]);

        $build->buildErrors()->save(BuildError::factory()->make([
            'stdoutput' => implode("\n", [
                "/usr/bin/c++ -I{$sourceDirectory}/include -c {$sourceDirectory}/foo/a.cpp",
                "{$sourceDirectory}/foo/a.cpp:1:2: see {$sourceDirectory}/docs and {$sourceDirectory}/foo/b.cpp:3:4",
                "\"{$sourceDirectory}/x y.cpp\", \"{$sourceDirectory}/foo/c.cpp:5:6\"",
                "{$sourceDirectory}/docs,{$sourceDirectory}/foo/d.cpp:7:8",
                "{$sourceDirectory}/x y.cpp[NON-XML-CHAR-0x1B][0m [NON-XML-CHAR-0x1B][1m{$sourceDirectory}/foo/e.cpp:9:10",
                '/.../ omitted',
                '/.../repo/foo/f.cpp:11:12',
                'see /.../repo and /.../repo/foo/g.cpp:13:14',
            ]),
        ]));

        $this->browse(function (Browser $browser) use ($sourceDirectory, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 ERROR')
            ;

            $links = array_map(
                fn ($link) => [$link->getText(), $link->getAttribute('href')],
                $browser->elements('@stdout + pre a'),
            );
            self::assertSame([
                ["{$sourceDirectory}/foo/a.cpp:1:2", 'https://example.com/org/repo/blob/main/foo/a.cpp'],
                ["{$sourceDirectory}/foo/b.cpp:3:4", 'https://example.com/org/repo/blob/main/foo/b.cpp'],
                ["{$sourceDirectory}/foo/c.cpp:5:6", 'https://example.com/org/repo/blob/main/foo/c.cpp'],
                ["{$sourceDirectory}/foo/d.cpp:7:8", 'https://example.com/org/repo/blob/main/foo/d.cpp'],
                ["{$sourceDirectory}/foo/e.cpp:9:10", 'https://example.com/org/repo/blob/main/foo/e.cpp'],
                ['/.../repo/foo/f.cpp:11:12', 'https://example.com/org/repo/blob/main/foo/f.cpp'],
                ['/.../repo/foo/g.cpp:13:14', 'https://example.com/org/repo/blob/main/foo/g.cpp'],
            ], $links);
        });
    }

    public function testLinksPathsContainingSpacesAndUrlCharacters(): void
    {
        $this->project->cvsviewertype = 'github';
        $this->project->cvsurl = 'https://example.com/org/repo';
        $this->project->save();

        $sourceDirectory = '/absolute/path/to/source';
        $filePath = $sourceDirectory . '/foo bar/C#/baz?.cpp:10:20';

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'sourcedirectory' => $sourceDirectory,
        ]);

        $build->buildErrors()->save(BuildError::factory()->make([
            'stdoutput' => "{$filePath}: error",
        ]));

        $this->browse(function (Browser $browser) use ($filePath, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 ERROR')
                ->assertSeeLink($filePath)
                ->assertPresent('a[href="https://example.com/org/repo/blob/main/foo%20bar/C%23/baz%3F.cpp"]')
            ;
        });
    }

    public function testLinksPathWhichExtendsAnotherLinkedPath(): void
    {
        $this->project->cvsviewertype = 'github';
        $this->project->cvsurl = 'https://example.com/org/repo';
        $this->project->save();

        $sourceDirectory = '/absolute/path/to/source';
        $filePath1 = $sourceDirectory . '/foo/bar.cpp:1:2';
        $filePath2 = $sourceDirectory . '/foo/bar.cpp:1:23';

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'sourcedirectory' => $sourceDirectory,
        ]);

        $build->buildErrors()->save(BuildError::factory()->make([
            'stdoutput' => "{$filePath1}: error\n{$filePath2}: error",
        ]));

        $this->browse(function (Browser $browser) use ($filePath1, $filePath2, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText('1 ERROR')
            ;

            $links = array_map(fn ($link) => $link->getText(), $browser->elements('@stdout + pre a'));
            self::assertSame([$filePath1, $filePath2], $links);
        });
    }

    public function testShowsListOfSubProjects(): void
    {
        /** @var Build $parent_build */
        $parent_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Build $child_build_1 */
        $child_build_1 = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject1->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Build $child_build_2 */
        $child_build_2 = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject2->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $child_build_1->buildErrors()->save(BuildError::factory()->make());
        /** @var BuildError $buildError2 */
        $buildError2 = $child_build_2->buildErrors()->save(BuildError::factory()->make());

        $this->browse(function (Browser $browser) use ($buildError1, $buildError2, $parent_build): void {
            // The build summary card and build info box load separately and push the subprojects down when they
            // render, so wait for them before clicking to avoid clicking where the subproject used to be.
            $browser->visit("/builds/{$parent_build->id}/build")
                ->waitForText($this->subproject1->name)
                ->waitForText($this->subproject2->name)
                ->waitForText($parent_build->name)
                ->waitFor('@build-info')
                ->assertDontSee($buildError1->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
                ->click('@collapse-' . $this->subproject1->id . ' summary')
                ->waitForText($buildError1->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
            ;
        });
    }

    public function testWarningsAndErrorsBySubproject(): void
    {
        /** @var Build $parent_build */
        $parent_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Build $child_build_1 */
        $child_build_1 = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject1->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'builderrors' => 1,
            'buildwarnings' => 5,
        ]);

        /** @var Build $child_build_2 */
        $child_build_2 = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject2->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($parent_build): void {
            $browser->visit("/builds/{$parent_build->id}/build")
                ->waitForText($this->subproject1->name)
                ->waitForText($this->subproject2->name)
                ->assertSeeIn('@errors-' . $this->subproject1->id, '1')
                ->assertSeeIn('@warnings-' . $this->subproject1->id, '5')
            ;
        });
    }

    public function testShowsLabelsIfPresent(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make());

        /** @var Label $label1 */
        $label1 = $buildError1->labels()->save(Label::factory()->make());
        /** @var Label $label2 */
        $label2 = $buildError1->labels()->save(Label::factory()->make());

        $this->browse(function (Browser $browser) use ($label2, $label1, $build): void {
            $browser->visit("/builds/{$build->id}/build")
                ->waitForText($label1->text)
                ->waitForText($label2->text)
                ->assertSee($label1->text)
                ->assertSee($label2->text)
            ;
        });
    }

    public function testShowsNewAndFixedWarnings(): void
    {
        $buildName = Str::uuid()->toString();

        /** @var Build $previous_build */
        $previous_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => Carbon::now()->subDay(),
        ]);

        /** @var Build $current_build */
        $current_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => Carbon::now(),
        ]);

        $buildError1 = BuildError::factory()->make([
            'type' => 1,
        ]);
        $buildError2 = BuildError::factory()->make([
            'type' => 1,
        ]);
        $buildError3 = BuildError::factory()->make([
            'type' => 1,
        ]);

        $previous_build->buildErrors()->create($buildError1->toArray());
        $previous_build->buildErrors()->create($buildError2->toArray());
        $current_build->buildErrors()->create($buildError2->toArray());
        $current_build->buildErrors()->create($buildError3->toArray());

        $this->browse(function (Browser $browser) use ($buildError1, $buildError2, $buildError3, $current_build): void {
            $browser->visit("/builds/{$current_build->id}/build")
                ->waitForText('2 WARNINGS')
                ->assertSee($buildError2->stdoutput)
                ->assertSee($buildError3->stdoutput)
                ->assertDontSee($buildError1->stdoutput)
            ;

            $browser->visit("/builds/{$current_build->id}/build?onlydeltap")
                ->waitForText('1 NEW WARNING')
                ->assertSee($buildError3->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
                ->assertDontSee($buildError1->stdoutput)
            ;

            $browser->visit("/builds/{$current_build->id}/build?onlydeltan")
                ->waitForText('1 FIXED WARNING')
                ->assertSee($buildError1->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
                ->assertDontSee($buildError3->stdoutput)
            ;
        });
    }

    public function testShowsNewAndFixedErrors(): void
    {
        $buildName = Str::uuid()->toString();

        /** @var Build $previous_build */
        $previous_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => Carbon::now()->subDay(),
        ]);

        /** @var Build $current_build */
        $current_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => Carbon::now(),
        ]);

        $buildError1 = BuildError::factory()->make([
            'type' => 0,
        ]);
        $buildError2 = BuildError::factory()->make([
            'type' => 0,
        ]);
        $buildError3 = BuildError::factory()->make([
            'type' => 0,
        ]);

        $previous_build->buildErrors()->create($buildError1->toArray());
        $previous_build->buildErrors()->create($buildError2->toArray());
        $current_build->buildErrors()->create($buildError2->toArray());
        $current_build->buildErrors()->create($buildError3->toArray());

        $this->browse(function (Browser $browser) use ($buildError1, $buildError2, $buildError3, $current_build): void {
            $browser->visit("/builds/{$current_build->id}/build")
                ->waitForText('2 ERRORS')
                ->assertSee($buildError2->stdoutput)
                ->assertSee($buildError3->stdoutput)
                ->assertDontSee($buildError1->stdoutput)
            ;

            $browser->visit("/builds/{$current_build->id}/build?onlydeltap")
                ->waitForText('1 NEW ERROR')
                ->assertSee($buildError3->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
                ->assertDontSee($buildError1->stdoutput)
            ;

            $browser->visit("/builds/{$current_build->id}/build?onlydeltan")
                ->waitForText('1 FIXED ERROR')
                ->assertSee($buildError1->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
                ->assertDontSee($buildError3->stdoutput)
            ;
        });
    }

    public function testShowsNewAndFixedWarningsBySubproject(): void
    {
        $buildName = Str::uuid()->toString();
        $currentStartTime = Carbon::now();
        $previousStartTime = Carbon::now()->subDay();

        /** @var Build $previous_parent_build */
        $previous_parent_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => $previousStartTime,
        ]);

        /** @var Build $previous_child_build_1 */
        $previous_child_build_1 = $previous_parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject1->id,
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'builderrors' => 1,
            'buildwarnings' => 5,
            'type' => 'Nightly',
            'starttime' => $previousStartTime,
        ]);

        /** @var Build $previous_child_build_2 */
        $previous_child_build_2 = $previous_parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject2->id,
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => $previousStartTime,
        ]);

        /** @var Build $current_parent_build */
        $current_parent_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => $currentStartTime,
        ]);

        /** @var Build $current_child_build_1 */
        $current_child_build_1 = $current_parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject1->id,
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'builderrors' => 1,
            'buildwarnings' => 5,
            'type' => 'Nightly',
            'starttime' => $currentStartTime,
        ]);

        /** @var Build $current_child_build_2 */
        $current_child_build_2 = $current_parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject2->id,
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => $currentStartTime,
        ]);

        $buildError1 = BuildError::factory()->make([
            'type' => 1,
        ]);
        $buildError2 = BuildError::factory()->make([
            'type' => 1,
        ]);
        $buildError3 = BuildError::factory()->make([
            'type' => 1,
        ]);
        $buildError4 = BuildError::factory()->make([
            'type' => 1,
        ]);

        $previous_child_build_1->buildErrors()->create($buildError1->toArray());
        $previous_child_build_1->buildErrors()->create($buildError2->toArray());
        $previous_child_build_2->buildErrors()->create($buildError3->toArray());
        $current_child_build_1->buildErrors()->create($buildError2->toArray());
        $current_child_build_1->buildErrors()->create($buildError4->toArray());
        $current_child_build_2->buildErrors()->create($buildError3->toArray());

        $this->browse(function (Browser $browser) use (
            $buildError1,
            $buildError2,
            $buildError3,
            $buildError4,
            $buildName,
            $current_parent_build,
        ): void {
            // Wait for the build summary card and build info box before clicking, as in testShowsListOfSubProjects().
            $browser->visit("/builds/{$current_parent_build->id}/build")
                ->waitForText($this->subproject1->name)
                ->waitForText($buildName)
                ->waitFor('@build-info')
                ->click('@collapse-' . $this->subproject1->id . ' summary')
                ->with('@collapse-' . $this->subproject1->id, function (Browser $browser) use (
                    $buildError1,
                    $buildError2,
                    $buildError3,
                    $buildError4,
                ): void {
                    $browser->waitForText('2 WARNINGS')
                        ->assertDontSee($buildError1->stdoutput)
                        ->assertSee($buildError2->stdoutput)
                        ->assertDontSee($buildError3->stdoutput)
                        ->assertSee($buildError4->stdoutput)
                    ;
                })
                ->click('@collapse-' . $this->subproject2->id . ' summary')
                ->with('@collapse-' . $this->subproject2->id, function (Browser $browser) use (
                    $buildError1,
                    $buildError2,
                    $buildError3,
                    $buildError4,
                ): void {
                    $browser->waitForText('1 WARNING')
                        ->assertDontSee($buildError1->stdoutput)
                        ->assertDontSee($buildError2->stdoutput)
                        ->assertSee($buildError3->stdoutput)
                        ->assertDontSee($buildError4->stdoutput)
                    ;
                })
            ;

            $browser->visit("/builds/{$current_parent_build->id}/build?onlydeltap")
                ->waitForText($this->subproject1->name)
                ->waitForText($buildName)
                ->waitFor('@build-info')
                ->click('@collapse-' . $this->subproject1->id . ' summary')
                ->with('@collapse-' . $this->subproject1->id, function (Browser $browser) use (
                    $buildError1,
                    $buildError2,
                    $buildError3,
                    $buildError4,
                ): void {
                    $browser->waitForText('1 NEW WARNING')
                        ->assertDontSee($buildError1->stdoutput)
                        ->assertDontSee($buildError2->stdoutput)
                        ->assertDontSee($buildError3->stdoutput)
                        ->assertSee($buildError4->stdoutput)
                    ;
                })
                ->click('@collapse-' . $this->subproject2->id . ' summary')
                ->with('@collapse-' . $this->subproject2->id, function (Browser $browser) use (
                    $buildError1,
                    $buildError2,
                    $buildError3,
                    $buildError4,
                ): void {
                    $browser->waitForText('No new errors or warnings for this build.')
                        ->assertDontSee($buildError1->stdoutput)
                        ->assertDontSee($buildError2->stdoutput)
                        ->assertDontSee($buildError3->stdoutput)
                        ->assertDontSee($buildError4->stdoutput)
                    ;
                })
            ;

            $browser->visit("/builds/{$current_parent_build->id}/build?onlydeltan")
                ->waitForText($this->subproject1->name)
                ->waitForText($buildName)
                ->waitFor('@build-info')
                ->click('@collapse-' . $this->subproject1->id . ' summary')
                ->with('@collapse-' . $this->subproject1->id, function (Browser $browser) use (
                    $buildError1,
                    $buildError2,
                    $buildError3,
                    $buildError4,
                ): void {
                    $browser->waitForText('1 FIXED WARNING')
                        ->assertSee($buildError1->stdoutput)
                        ->assertDontSee($buildError2->stdoutput)
                        ->assertDontSee($buildError3->stdoutput)
                        ->assertDontSee($buildError4->stdoutput)
                    ;
                })
                ->click('@collapse-' . $this->subproject2->id . ' summary')
                ->with('@collapse-' . $this->subproject2->id, function (Browser $browser): void {
                    $browser->waitForText('No fixed errors or warnings for this build.');
                })
            ;
        });
    }
}
