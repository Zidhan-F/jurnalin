<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

final readonly class AssessmentRubric
{
    /**
     * @param  array{industry_percentage:int, school_percentage:int, attendance_percentage:int}  $weights
     * @param  list<array{code:string, label:string, weight:int}>  $industryAspects
     * @param  list<array{code:string, label:string, weight:int}>  $schoolAspects
     * @param  list<array{min:int, label:string}>  $predicates
     */
    public function __construct(
        public array $weights,
        public array $industryAspects,
        public array $schoolAspects,
        public array $predicates,
    ) {
        $totalMainWeight = (int) ($weights['industry_percentage'] ?? 0)
            + (int) ($weights['school_percentage'] ?? 0)
            + (int) ($weights['attendance_percentage'] ?? 0);

        if ($totalMainWeight !== 100) {
            throw new InvalidArgumentException("Total bobot utama (Industri + Sekolah + Kehadiran) wajib 100%, saat ini {$totalMainWeight}%.");
        }

        $industrySum = array_sum(array_column($industryAspects, 'weight'));
        if ($industrySum !== 100 || count($industryAspects) === 0) {
            throw new InvalidArgumentException("Total bobot aspek industri wajib 100%, saat ini {$industrySum}%.");
        }

        $schoolSum = array_sum(array_column($schoolAspects, 'weight'));
        if ($schoolSum !== 100 || count($schoolAspects) === 0) {
            throw new InvalidArgumentException("Total bobot aspek sekolah wajib 100%, saat ini {$schoolSum}%.");
        }
    }

    /**
     * @param  array<string, mixed>  $template
     */
    public static function fromArray(array $template): self
    {
        return new self(
            weights: $template['weights'] ?? [],
            industryAspects: $template['industry_aspects'] ?? [],
            schoolAspects: $template['school_aspects'] ?? [],
            predicates: $template['predicates'] ?? self::defaultTemplate()['predicates'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultTemplate(): array
    {
        return [
            'scale' => ['min' => 0, 'max' => 100],
            'weights' => [
                'industry_percentage' => 70,
                'school_percentage' => 20,
                'attendance_percentage' => 10,
            ],
            'industry_aspects' => [
                ['code' => 'soft_skills', 'label' => 'Sikap kerja (disiplin, komunikasi, etika)', 'weight' => 40],
                ['code' => 'hard_skills', 'label' => 'Keterampilan teknis sesuai prosedur kerja', 'weight' => 45],
                ['code' => 'initiative', 'label' => 'Kemandirian & Inisiatif Kerja', 'weight' => 15],
            ],
            'school_aspects' => [
                ['code' => 'journal_quality', 'label' => 'Kelengkapan & Kualitas Jurnal Harian', 'weight' => 50],
                ['code' => 'report_presentation', 'label' => 'Laporan Akhir & Sidang PKL', 'weight' => 50],
            ],
            'predicates' => [
                ['min' => 90, 'label' => 'Amat Baik'],
                ['min' => 80, 'label' => 'Baik'],
                ['min' => 70, 'label' => 'Cukup'],
                ['min' => 0, 'label' => 'Kurang'],
            ],
        ];
    }

    /**
     * Template bawaan berbasis Tujuan Pembelajaran PKL Kurikulum Merdeka (P39).
     * Nilai industri per TP diisi dari formulir pembimbing lapangan; nilai sekolah dari guru.
     *
     * @return array<string, mixed>
     */
    public static function tpTemplate(): array
    {
        $tp = config('pkl.default_objectives');

        return [
            'kind' => 'tp',
            'scale' => ['min' => 0, 'max' => 100],
            'weights' => ['industry_percentage' => 60, 'school_percentage' => 30, 'attendance_percentage' => 10],
            'industry_aspects' => [
                ['code' => 'TP1', 'label' => $tp[0]['label'], 'weight' => 25],
                ['code' => 'TP2', 'label' => $tp[1]['label'], 'weight' => 20],
                ['code' => 'TP3', 'label' => $tp[2]['label'], 'weight' => 35],
                ['code' => 'TP4', 'label' => $tp[3]['label'], 'weight' => 20],
            ],
            'school_aspects' => [
                ['code' => 'TP5', 'label' => $tp[4]['label'], 'weight' => 30],
                ['code' => 'journal_quality', 'label' => 'Kelengkapan & kualitas jurnal harian', 'weight' => 40],
                ['code' => 'report_presentation', 'label' => 'Laporan akhir & presentasi PKL', 'weight' => 30],
            ],
            'predicates' => self::defaultTemplate()['predicates'],
        ];
    }

    /**
     * Deskripsi capaian gaya rapor Kurikulum Merdeka: aspek tertinggi dan aspek yang perlu ditingkatkan.
     * Disusun dari nilai yang diinput manusia, bukan dari jumlah jurnal.
     *
     * @param  array<string, float|int>  $industryScores
     * @param  array<string, float|int>  $schoolScores
     */
    public function describeAchievement(string $studentName, array $industryScores, array $schoolScores): string
    {
        $rows = [];
        foreach ([[$this->industryAspects, $industryScores], [$this->schoolAspects, $schoolScores]] as [$aspects, $scores]) {
            foreach ($aspects as $aspect) {
                if (isset($scores[$aspect['code']])) {
                    $rows[] = ['label' => $this->shortLabel($aspect['label']), 'score' => (float) $scores[$aspect['code']]];
                }
            }
        }
        if ($rows === []) {
            return '';
        }
        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);
        $strong = array_values(array_filter($rows, fn ($r) => $r['score'] >= 85));
        $weak = array_values(array_filter($rows, fn ($r) => $r['score'] < 75));
        $strong = $strong ?: [$rows[0]];
        $text = "{$studentName} menunjukkan penguasaan yang ".($strong[0]['score'] >= 85 ? 'sangat baik' : 'baik').' dalam '.$this->joinLabels(array_slice($strong, 0, 2));
        $text .= $weak ? ', dan perlu peningkatan dalam '.$this->joinLabels(array_slice(array_reverse($weak), 0, 2)).'.' : '.';

        return $text;
    }

    private function shortLabel(string $label): string
    {
        $label = preg_replace('/^(Menerapkan|Memahami|Penerapan|Internalisasi)\s+/u', '', $label) ?? $label;
        $label = preg_replace('/\s*\(.*\)\s*/u', ' ', $label) ?? $label;

        return mb_strtolower(trim($label));
    }

    /** @param list<array{label:string}> $rows */
    private function joinLabels(array $rows): string
    {
        $labels = array_column($rows, 'label');

        return count($labels) > 1 ? implode(', ', array_slice($labels, 0, -1)).' serta '.end($labels) : ($labels[0] ?? '');
    }

    /**
     * @param  array<string, float|int>  $aspectScores
     * @param  list<array{code:string, label:string, weight:int}>  $aspects
     */
    public function calculateGroupAverage(array $aspectScores, array $aspects): float
    {
        $sum = 0.0;
        foreach ($aspects as $aspect) {
            $code = $aspect['code'];
            $weight = (float) $aspect['weight'];
            $score = (float) ($aspectScores[$code] ?? 0);

            if ($score < 0 || $score > 100) {
                throw new InvalidArgumentException("Skor untuk {$aspect['label']} harus berada di antara 0 sampai 100.");
            }

            $sum += ($score * $weight) / 100.0;
        }

        return round($sum, 2);
    }

    public function calculateFinalScore(float $industryAverage, float $schoolAverage, float $attendanceScore): float
    {
        $final = ($industryAverage * ($this->weights['industry_percentage'] / 100.0))
            + ($schoolAverage * ($this->weights['school_percentage'] / 100.0))
            + ($attendanceScore * ($this->weights['attendance_percentage'] / 100.0));

        return round($final, 2);
    }

    public function resolvePredicate(float $finalScore): string
    {
        $sorted = $this->predicates;
        usort($sorted, fn (array $a, array $b) => $b['min'] <=> $a['min']);

        foreach ($sorted as $tier) {
            if ($finalScore >= (float) $tier['min']) {
                return (string) $tier['label'];
            }
        }

        return 'Kurang';
    }
}
