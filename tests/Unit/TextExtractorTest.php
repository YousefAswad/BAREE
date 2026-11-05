<?php

use App\Services\Files\TextExtractor;

function officeFile(array $entries): string
{
    $path = tempnam(sys_get_temp_dir(), 'ofx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    foreach ($entries as $name => $xml) {
        $zip->addFromString($name, $xml);
    }
    $zip->close();

    return $path;
}

it('extracts Arabic paragraphs from a Word file', function () {
    $path = officeFile(['word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>المعادلات الخطية</w:t></w:r></w:p><w:p><w:r><w:t>الدرس الثاني &amp; التمارين</w:t></w:r></w:p></w:body></w:document>']);

    $text = (new TextExtractor)->extract($path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    expect($text)->toContain('المعادلات الخطية')->toContain('الدرس الثاني & التمارين');
});

it('reads slides in numeric order and spreadsheet strings', function () {
    $pptx = officeFile(['ppt/slides/slide10.xml' => '<p:sld><a:p><a:t>عاشرة</a:t></a:p></p:sld>', 'ppt/slides/slide2.xml' => '<p:sld><a:p><a:t>ثانية</a:t></a:p></p:sld>']);
    $xlsx = officeFile(['xl/sharedStrings.xml' => '<sst><si><t>الاسم</t></si><si><t>العلامة</t></si></sst>']);
    $extractor = new TextExtractor;

    expect(strpos($extractor->extract($pptx, 'application/vnd.openxmlformats-officedocument.presentationml.presentation'), 'ثانية'))
        ->toBeLessThan(strpos($extractor->extract($pptx, 'application/vnd.openxmlformats-officedocument.presentationml.presentation'), 'عاشرة'))
        ->and($extractor->extract($xlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))->toContain('العلامة');
});
