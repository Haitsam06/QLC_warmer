<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MitraReport;
use App\Models\Notification;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MitraReportController extends Controller
{
    public function partners(Request $request): JsonResponse
    {
        $search = trim($request->query('search', ''));

        $query = Partner::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('institution_name', 'like', '%' . $search . '%')
                  ->orWhere('contact_person', 'like', '%' . $search . '%');
            });
        }

        $partners = $query->orderBy('institution_name')->get();

        $partnerIds  = $partners->pluck('id')->map(fn($id) => (string) $id)->toArray();
        $reportCount = [];

        if (!empty($partnerIds)) {
            MitraReport::whereIn('partner_id', $partnerIds)
                ->get(['partner_id'])
                ->groupBy('partner_id')
                ->each(function ($group, $pid) use (&$reportCount) {
                    $reportCount[$pid] = $group->count();
                });
        }

        $data = $partners->map(function ($doc) use ($reportCount) {
            $pid = (string) $doc->id;
            return [
                'id'               => $pid,
                'institution_name' => $doc->institution_name ?? '—',
                'contact_person'   => $doc->contact_person   ?? '—',
                'status'           => $doc->status           ?? 'Inactive',
                'report_count'     => $reportCount[$pid]     ?? 0,
            ];
        });

        return response()->json($data);
    }

    public function reports(string $partnerId): JsonResponse
    {
        $data = MitraReport::where('partner_id', $partnerId)
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn($r) => $this->formatReport($r));

        return response()->json($data);
    }

    public function store(Request $request, string $partnerId): JsonResponse
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'date'        => 'required|date_format:Y-m-d',
            'description' => 'nullable|string|max:2000',
            'file'        => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $partner = Partner::find($partnerId);
        if (!$partner) {
            return response()->json(['message' => 'Mitra tidak ditemukan.'], 404);
        }

        $file        = $request->file('file');
        $path        = $file->store('mitra-reports', 'public');
        $fileUrl     = url('storage/' . $path);

        $rawBaseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeBase    = substr(trim(preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $rawBaseName)) ?: 'file', 0, 200);
        $safeExt     = preg_replace('/[^a-zA-Z0-9]/', '', $file->getClientOriginalExtension());
        $fileName    = $safeBase . ($safeExt ? '.' . $safeExt : '');

        $report = MitraReport::create([
            'partner_id'  => $partner->id,
            'title'       => $request->title,
            'date'        => $request->date,
            'description' => $request->description ?? null,
            'file_url'    => $fileUrl,
            'file_path'   => $path,
            'file_name'   => $fileName,
            'file_type'   => $safeExt,
            'file_size'   => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        $mitraUserId = $partner->user_id ?? null;
        if ($mitraUserId) {
            Notification::send(
                $mitraUserId,
                'info',
                'Laporan Kerja Sama Baru',
                "Admin telah mengunggah laporan: \"{$request->title}\" tertanggal {$request->date}.",
                'laporan'
            );
        }

        return response()->json($this->formatReport($report), 201);
    }

    public function destroy(string $reportId): JsonResponse
    {
        $report = MitraReport::find($reportId);

        if (!$report) {
            return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
        }

        if (!empty($report->file_path)) {
            Storage::disk('public')->delete($report->file_path);
        }

        $report->delete();

        return response()->json(['message' => 'Laporan berhasil dihapus.']);
    }

    public function mitraReports(Request $request): JsonResponse
    {
        $userId  = (string) auth()->id();
        $partner = Partner::where('user_id', $userId)->first();

        if (!$partner) {
            return response()->json(['message' => 'Profil mitra tidak ditemukan.'], 404);
        }

        $partnerId = $partner->id;
        $search    = trim($request->query('search', ''));
        $perPage   = max(1, min(100, (int) $request->query('per_page', 10)));
        $page      = max(1, (int) $request->query('page', 1));
        $skip      = ($page - 1) * $perPage;

        $query = MitraReport::where('partner_id', $partnerId);

        if ($search !== '') {
            $query->where('title', 'like', '%' . $search . '%');
        }

        $total = $query->count();

        $sevenDaysAgo = now()->subDays(7)->format('Y-m-d');
        $newCount     = MitraReport::where('partner_id', $partnerId)
            ->where('date', '>=', $sevenDaysAgo)
            ->count();

        $data = $query->orderBy('date', 'desc')->skip($skip)->take($perPage)->get()
            ->map(fn($r) => $this->formatReport($r));

        return response()->json([
            'data'      => $data,
            'new_count' => $newCount,
            'meta'      => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => (int) ceil($total / max($perPage, 1)),
            ],
        ]);
    }

    private function formatReport($doc): array
    {
        return [
            'id'          => $doc->id,
            'partner_id'  => $doc->partner_id ?? '',
            'title'       => $doc->title       ?? '—',
            'date'        => $doc->date         ?? null,
            'description' => $doc->description  ?? null,
            'file_url'    => $doc->file_url     ?? null,
            'file_name'   => $doc->file_name    ?? null,
            'file_type'   => $doc->file_type    ?? null,
            'file_size'   => $doc->file_size    ?? null,
            'created_at'  => $doc->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
