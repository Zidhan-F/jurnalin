<?php

declare(strict_types=1);

namespace App\Support;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Models\AiRequest;
use App\Models\User;
use Throwable;

/**
 * Tata kelola AI (S12). Hanya dipanggil bila add-on AI aktif dan ANTHROPIC_API_KEY tersedia; tanpa itu
 * pemanggil memakai penyusun berbasis aturan. AI tidak boleh mengarang kegiatan, nilai, atau persetujuan:
 * keluaran selalu draf yang wajib diperiksa manusia. Setiap permintaan dicatat (fitur, panjang, status)
 * tanpa menyimpan isi.
 */
final class AiAssistant
{
    public const GUARDRAIL = 'Anda membantu siswa SMK Indonesia menulis catatan PKL. Gunakan bahasa Indonesia baku yang sederhana. '
        .'Jangan menambahkan kegiatan, angka, hasil, nama orang, atau persetujuan yang tidak ada di masukan pengguna. '
        .'Bila informasi kurang, tulis [lengkapi: ...] alih-alih mengarang. Jangan pernah memberi nilai, prediksi kelulusan, atau penilaian psikologis.';

    public static function available(User $user): bool
    {
        return $user->school?->hasFeature('ai') === true && filled(config('services.anthropic.key'));
    }

    /** Mengembalikan teks draf, atau null bila AI tidak tersedia/gagal (pemanggil memakai cadangan). */
    public static function complete(User $user, string $feature, string $prompt, int $maxTokens = 2000): ?string
    {
        if (! self::available($user)) {
            return null;
        }
        $model = (string) config('services.anthropic.model');
        $log = fn (string $status, int $out = 0) => AiRequest::query()->create([
            'school_id' => $user->school_id, 'user_id' => $user->id, 'feature' => $feature, 'model' => $model,
            'input_chars' => mb_strlen($prompt), 'output_chars' => $out, 'status' => $status,
        ]);

        try {
            $client = new Client(apiKey: (string) config('services.anthropic.key'));
            $message = $client->messages->create(
                model: $model,
                maxTokens: $maxTokens,
                system: self::GUARDRAIL,
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: ['effort' => 'low'],
            );
            if ($message->stopReason === 'refusal') {
                $log('refused');

                return null;
            }
            $text = '';
            foreach ($message->content as $block) {
                if ($block->type === 'text') {
                    $text .= $block->text;
                }
            }
            $log('ok', mb_strlen($text));

            return trim($text) !== '' ? trim($text) : null;
        } catch (APIException $e) {
            report($e);
            $log('error');
        } catch (Throwable $e) {
            report($e);
            $log('error');
        }

        return null;
    }
}
