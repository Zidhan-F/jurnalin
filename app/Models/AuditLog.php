<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan siapa melakukan apa dan kapan. Hanya ditambah, tidak pernah diubah. */
class AuditLog extends Model
{
    use BelongsToSchool, HasUuids;

    public const UPDATED_AT = null;

    public const LABELS = [
        'plan.upgraded' => 'Menaikkan paket ke Pro',
        'plan.downgraded' => 'Menurunkan paket ke Reguler',
        'plan.downgrade_scheduled' => 'Menjadwalkan turun paket ke Reguler',
        'plan.downgrade_cancelled' => 'Membatalkan jadwal turun paket',
        'group.created' => 'Membuat grup siswa',
        'group.stopped' => 'Menonaktifkan akses grup',
        'payment.submitted' => 'Mengirim bukti pembayaran',
        'payment.verified' => 'Memverifikasi pembayaran',
        'payment.returned' => 'Mengembalikan bukti pembayaran',
        'journal.approved' => 'Menyetujui jurnal',
        'journal.revision_requested' => 'Meminta revisi jurnal',
        'attendance.exception_approved' => 'Menyetujui pengecualian absensi',
        'assessment.saved' => 'Menyimpan draf nilai',
        'assessment.finalized' => 'Memfinalisasi nilai',
        'placement.status_changed' => 'Mengubah status penempatan',
        'user.password_reset_by_admin' => 'Mereset kata sandi pengguna',
        'user.password_reset_by_platform' => 'Admin Jurnalin mereset kata sandi admin sekolah',
        'user.updated' => 'Mengubah data pengguna',
        'user.deactivated' => 'Menonaktifkan akun',
        'user.activated' => 'Mengaktifkan kembali akun',
        'mentor_link.created' => 'Membuat tautan tanda tangan DUDI',
        'mentor_link.revoked' => 'Mencabut tautan tanda tangan DUDI',
        'mentor.signed' => 'Pembimbing DUDI menandatangani jurnal',
        'mentor.noted' => 'Pembimbing DUDI memberi catatan jurnal',
        'school.exported' => 'Mengunduh ekspor data sekolah',
        'school.deletion_requested' => 'Mengajukan penghapusan data sekolah',
        'school.approved' => 'Memverifikasi sekolah',
        'school.status_changed' => 'Mengubah status sekolah',
        'school.module_toggled' => 'Mengubah aktivasi modul sekolah',
        'school.workflow_updated' => 'Mengubah pengaturan alur sekolah',
        'user.magic_link_issued' => 'Membuat tautan masuk',
        'leave.submitted' => 'Mengajukan izin/sakit',
        'leave.approved' => 'Menyetujui izin/sakit',
        'leave.rejected' => 'Menolak izin/sakit',
        'attendance.reviewed' => 'Meninjau presensi bertanda',
        'journal.reopened' => 'Membuka ulang jurnal',
        'assessment.revision_opened' => 'Membuka revisi nilai berversi',
        'period.policy_updated' => 'Mengubah kebijakan periode',
        'period.archived' => 'Mengarsipkan periode',
        'period.activated' => 'Mengaktifkan periode',
        'placement.location_updated' => 'Mengubah lokasi/status penempatan',
        'visit.recorded' => 'Mencatat kunjungan',
        'ticket.created' => 'Siswa membuat laporan Lapor ke Guru',
        'ticket.updated' => 'Menindaklanjuti laporan siswa',
        'privacy.request' => 'Mengajukan permintaan data pribadi',
        'privacy.answered' => 'Menjawab permintaan data pribadi',
        'privacy.exported' => 'Mengunduh data pribadi sendiri',
        'privacy.dpa_accepted' => 'Menyetujui perjanjian pemrosesan data',
        'consent.recorded' => 'Mencatat persetujuan wali',
        'consent.given_digital' => 'Wali memberi persetujuan digital',
        'consent.declined' => 'Wali menolak persetujuan',
        'consent.withdrawn' => 'Mencatat pencabutan persetujuan wali',
        'import.processed' => 'Memproses impor data',
        'certificate.issued' => 'Menerbitkan sertifikat',
        'certificate.revoked' => 'Mencabut sertifikat',
        'workproof.submitted' => 'Mengajukan Bukti Kerja',
        'workproof.reviewed' => 'Meninjau Bukti Kerja',
        'objectives.verified' => 'Memverifikasi klaim TP',
        'contact.created' => 'Mencatat kontak pembimbing lapangan',
        'mentor_access.issued' => 'Membuat tautan pembimbing industri',
        'mentor_access.revoked' => 'Mencabut tautan pembimbing industri',
        'mentor.journal_comment' => 'Pembimbing lapangan memberi catatan',
        'mentor.journal_approved' => 'Pembimbing lapangan menyetujui jurnal',
        'mentor.journal_revision_needed' => 'Pembimbing lapangan meminta revisi',
        'mentor.evaluation_final' => 'Pembimbing lapangan mengesahkan evaluasi',
        'guest.otp_verified' => 'Tamu memverifikasi kode OTP',
        'guest.otp_failed' => 'Percobaan OTP gagal',
        'passport.updated' => 'Memperbarui Paspor Kompetensi',
        'passport.revoked' => 'Mencabut tautan Paspor Kompetensi',
        'partner.status_changed' => 'Mengubah status kemitraan',
        'partner.agreement_added' => 'Mengarsipkan dokumen kerja sama',
        'partner.responded' => 'PIC mitra mengisi formulir',
        'hubin.bulk_placed' => 'Penempatan massal',
        'hubin.mentor_reassigned' => 'Memindahkan guru pembimbing',
        'hubin.dudi_opt_in' => 'Mengubah keikutsertaan Peta DUDI',
        'internship.created' => 'Membuat program magang guru',
        'internship.journal_reviewed' => 'Meninjau jurnal magang guru',
        'ukk.instrument_updated' => 'Mengubah instrumen UKK',
        'ukk.examiner_invited' => 'Mengundang penguji UKK',
        'ukk.scored_external' => 'Penguji eksternal menilai UKK',
        'ukk.finalized' => 'Memfinalkan rekap UKK',
        'tracer.graduated' => 'Menandai kelulusan',
        'tracer.campaign_created' => 'Membuat kampanye tracer',
        'tracer.exported' => 'Mengekspor rekap tracer',
    ];

    protected $fillable = ['school_id', 'user_id', 'actor_label', 'action', 'subject_type', 'subject_id', 'meta', 'ip'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $action, ?Model $subject = null, array $meta = [], ?string $schoolId = null, ?string $actorLabel = null): void
    {
        self::query()->create([
            'school_id' => $schoolId ?? $subject?->getAttribute('school_id') ?? TenantContext::currentSchoolId() ?? auth()->user()?->school_id,
            'user_id' => auth()->id(),
            'actor_label' => $actorLabel,
            'action' => $action,
            'subject_type' => $subject !== null ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
