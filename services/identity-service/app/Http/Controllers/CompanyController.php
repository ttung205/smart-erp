<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    private const PROFILE_ID = 1;

    public function show(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->profile()]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'tax_code' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'office_address' => ['nullable', 'string', 'max:500'],
            'tel' => ['nullable', 'string', 'max:50'],
            'hotline' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'accounting_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_beneficiary' => ['nullable', 'string', 'max:255'],
        ]);

        $this->saveProfile($validated);

        return $this->show();
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048']]);
        $file = $request->file('logo');
        $this->saveProfile([
            'logo_data' => base64_encode($file->get()),
            'logo_mime' => $file->getMimeType(),
        ]);

        return $this->show();
    }

    public function deleteLogo(): JsonResponse
    {
        DB::table('company_profiles')->where('id', self::PROFILE_ID)
            ->update(['logo_data' => null, 'logo_mime' => null, 'updated_at' => now()]);

        return $this->show();
    }

    public function logo(): Response
    {
        $row = DB::table('company_profiles')->where('id', self::PROFILE_ID)->first();
        $bytes = $row?->logo_data ? base64_decode($row->logo_data, true) : false;
        if ($bytes === false) {
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type' => $row->logo_mime ?: 'image/png',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function profile(): array
    {
        $row = DB::table('company_profiles')->where('id', self::PROFILE_ID)->first();
        $data = $row ? (array) $row : [];
        unset($data['logo_data'], $data['logo_mime']);
        $data['logo_url'] = $row?->logo_data
            ? '/api/v1/auth/company/logo?v='.substr(hash('sha256', $row->logo_data), 0, 12)
            : null;

        return $data;
    }

    private function saveProfile(array $values): void
    {
        $query = DB::table('company_profiles')->where('id', self::PROFILE_ID);
        if ($query->exists()) {
            $query->update([...$values, 'updated_at' => now()]);
        } else {
            DB::table('company_profiles')->insert(['id' => self::PROFILE_ID, ...$values, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
