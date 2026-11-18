<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\MediaStorage;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ProfilePhotoController extends Controller
{
    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('profile.photo', [
            'user' => $user,
            'layout' => $user->role === UserRole::Student ? 'layouts.student-pwa' : 'layouts.app',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['nullable', 'required_without:photo_data_url', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'photo_data_url' => ['nullable', 'string', 'max:6000000'],
        ], [
            'photo.required_without' => 'Pilih foto profil terlebih dahulu.',
            'photo.image' => 'Berkas harus berupa gambar (JPG, PNG, WebP, atau GIF).',
            'photo.mimes' => 'Format foto profil harus JPG, PNG, WebP, atau GIF.',
            'photo.max' => 'Ukuran foto maksimal 4 MB.',
        ]);

        /** @var User $user */
        $user = $request->user();
        $oldPath = $user->avatar_path;

        $newPath = MediaStorage::storeUserAvatar(
            user: $user,
            file: $request->file('photo'),
            base64DataUrl: $request->string('photo_data_url')->toString() ?: null,
        );

        if ($newPath === null) {
            return back()->withErrors(['photo' => 'Berkas foto tidak valid. Gunakan gambar JPG, PNG, WebP, atau GIF.']);
        }

        TenantContext::runForUser($user, function () use ($user, $newPath): void {
            $user->forceFill(['avatar_path' => $newPath])->save();
            AuditLog::record('user.avatar_updated', $user);
        });

        if ($oldPath !== null && $oldPath !== $newPath) {
            MediaStorage::discard($oldPath);
        }

        return redirect()->route('profile.photo.edit')->with('status', 'Foto profil pribadi berhasil diperbarui.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $oldPath = $user->avatar_path;

        if ($oldPath !== null) {
            TenantContext::runForUser($user, function () use ($user): void {
                $user->forceFill(['avatar_path' => null])->save();
                AuditLog::record('user.avatar_removed', $user);
            });
            MediaStorage::discard($oldPath);
        }

        return redirect()->route('profile.photo.edit')->with('status', 'Foto profil dikembalikan ke avatar bawaan.');
    }
}
