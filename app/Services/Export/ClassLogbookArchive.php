<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\PklPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

final class ClassLogbookArchive
{
    public function download(Collection $placements, PklPeriod $period, string $className): BinaryFileResponse
    {
        $archive = new ZipArchive;
        $path = tempnam(sys_get_temp_dir(), 'jurnalin-kelas-');
        $files = [];
        try {
            if ($archive->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Arsip jurnal tidak dapat dibuat.');
            }
            foreach ($placements as $placement) {
                $pdf = tempnam(sys_get_temp_dir(), 'jurnalin-pdf-');
                $files[] = $pdf;
                (new StudentLogbookPdfGenerator($placement->withoutRelations()))->save($pdf);
                $name = Str::slug($placement->student->user->name).'-'.$placement->id.'.pdf';
                if (! $archive->addFile($pdf, $name)) {
                    throw new RuntimeException('PDF siswa tidak dapat ditambahkan ke arsip.');
                }
            }
            if (! $archive->close()) {
                throw new RuntimeException('Arsip jurnal tidak dapat disimpan.');
            }

            return response()->download($path, 'jurnal-'.Str::slug($className.'-'.$period->name).'.zip')->deleteFileAfterSend(true);
        } catch (Throwable $error) {
            unset($archive);
            unlink($path);
            throw $error;
        } finally {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}
