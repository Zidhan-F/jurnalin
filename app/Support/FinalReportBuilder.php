<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\JournalStatus;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\WorkProof;

/**
 * Laporan Akhir Otomatis (P27). Disusun hanya dari catatan yang punya sumber (jurnal disetujui,
 * Bukti Kerja, TP terverifikasi). Setiap kutipan merujuk tanggal jurnal; hal yang tidak diketahui
 * ditulis sebagai placeholder [Lengkapi: ...]. Wajib ditinjau manusia sebelum dipakai.
 */
final class FinalReportBuilder
{
    /** @return array{chapters: list<array{title:string, paragraphs:list<string>}>, sources:int, placeholders:int} */
    public static function build(Placement $placement): array
    {
        $placement->loadMissing(['student.user', 'company', 'pklPeriod', 'school', 'teacher.user']);
        $journals = Journal::query()->where('placement_id', $placement->id)->where('status', JournalStatus::Approved)->orderBy('journal_date')->get();
        $proofs = WorkProof::query()->where('placement_id', $placement->id)->where('status', 'verified')->get();
        $objectives = LearningObjectives::progress($placement->school, $placement->id);
        $c = $placement->company;
        $ph = fn (string $what) => "[Lengkapi: {$what}]";

        $activities = [];
        foreach ($journals->groupBy('work_unit') as $unit => $items) {
            $titles = $items->take(6)->map(fn ($j) => $j->activity_title.' (jurnal '.$j->journal_date->format('d/m/Y').')')->implode('; ');
            $activities[] = "Di unit {$unit}, kegiatan yang tercatat antara lain: {$titles}.".($items->count() > 6 ? ' Ditambah '.($items->count() - 6).' kegiatan lain pada lampiran jurnal.' : '');
        }
        $learned = $journals->pluck('learning_outcome')->filter()->unique()->take(6)->values();
        $obstacles = $journals->filter(fn ($j) => filled($j->obstacles))->take(5)->map(fn ($j) => $j->obstacles.' (jurnal '.$j->journal_date->format('d/m/Y').')');

        $chapters = [
            ['title' => 'BAB I Pendahuluan', 'paragraphs' => [
                "Praktik Kerja Lapangan (PKL) dilaksanakan oleh {$placement->student->user->name} dari program keahlian {$placement->student->major} di {$c->name} pada periode {$placement->pklPeriod->name} ({$placement->pklPeriod->start_date->format('d/m/Y')} sampai {$placement->pklPeriod->end_date->format('d/m/Y')}).",
                $ph('latar belakang dan tujuan PKL sesuai pedoman sekolah'),
            ]],
            ['title' => 'BAB II Profil Tempat PKL', 'paragraphs' => [
                "{$c->name} bergerak di bidang {$c->industry_type} dan berlokasi di {$c->address}, {$c->city}.",
                $ph('sejarah singkat, struktur organisasi, dan produk atau layanan tempat PKL'),
            ]],
            ['title' => 'BAB III Kegiatan PKL', 'paragraphs' => $activities ?: [$ph('belum ada jurnal disetujui sebagai sumber kegiatan')]],
            ['title' => 'BAB IV Capaian Tujuan Pembelajaran', 'paragraphs' => array_values(array_filter([
                ...$objectives->map(fn ($r) => "{$r['objective']->code} {$r['objective']->label}: ".($r['verified'] > 0 ? "{$r['verified']} bukti jurnal terverifikasi pembimbing." : 'belum ada bukti terverifikasi. '.$ph('uraian capaian atau alasan'))),
                ...$proofs->map(fn ($w) => "Bukti Kerja \"{$w->title}\": {$w->result}"),
                $learned->isNotEmpty() ? 'Pelajaran yang dicatat siswa: '.$learned->implode('; ').'.' : null,
            ]))],
            ['title' => 'BAB V Kendala dan Solusi', 'paragraphs' => $obstacles->isNotEmpty() ? [...$obstacles->values()->all(), $ph('solusi yang dilakukan untuk setiap kendala')] : [$ph('kendala selama PKL dan cara mengatasinya')]],
            ['title' => 'BAB VI Penutup', 'paragraphs' => [$ph('kesimpulan'), $ph('saran untuk tempat PKL dan sekolah')]],
        ];

        $placeholders = collect($chapters)->flatMap(fn ($ch) => $ch['paragraphs'])->filter(fn ($p) => str_contains($p, '[Lengkapi:'))->count();

        return ['chapters' => $chapters, 'sources' => $journals->count() + $proofs->count(), 'placeholders' => $placeholders];
    }
}
