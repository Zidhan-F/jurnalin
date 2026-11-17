<details class="border-t border-border">
    <summary class="cursor-pointer py-4 font-semibold text-danger">{{ $target ? 'Nonaktifkan grup '.$target->name : 'Nonaktifkan akses seluruh sekolah' }}</summary>
    <form method="POST" action="{{ $stopUrl }}" class="grid max-w-xl gap-3 pb-3" data-question="Nonaktifkan {{ $target ? 'grup '.$target->name : 'akses seluruh sekolah' }} sekarang?" onsubmit="return window.confirm(this.dataset.question)">
        @csrf
        <p class="font-semibold">{{ $target ? 'Akses ke '.$target->name.' akan berhenti untuk '.$target->members_count.' siswa dan guru pembimbingnya.' : 'Akses seluruh siswa dan guru di semua periode akan berhenti, termasuk siswa yang belum masuk grup.' }}</p>
        <ul class="list-disc space-y-1 pl-5 text-sm">
            <li>Pengguna yang kehilangan seluruh akses akan keluar dari sesi mereka.@if($target) Guru yang membimbing grup lain tetap dapat mengakses grup tersebut.@endif</li>
            <li>Tautan pembimbing yang terdampak dicabut. Pembayaran yang masih menunggu verifikasi dibatalkan.</li>
            <li>Untuk mengaktifkan grup kembali, sekolah perlu membayar penuh untuk periode baru.</li>
        </ul>
        <label class="flex min-h-12 items-center gap-3"><input type="checkbox" name="confirm_stop" value="1" required>
            Saya memahami dampaknya dan ingin menonaktifkan {{ $target ? $target->name : 'akses seluruh sekolah' }}.
        </label>
        <button class="min-h-12 justify-self-start rounded-md bg-danger px-4 font-semibold text-white">{{ $target ? 'Ya, nonaktifkan grup' : 'Ya, nonaktifkan seluruh sekolah' }}</button>
    </form>
</details>
