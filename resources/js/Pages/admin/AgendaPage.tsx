import { useState, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { ChevronLeft, ChevronRight, X, Pencil, Trash2, Loader2, CheckCircle2, MapPin, Link, Eye, Calendar, Plus } from 'lucide-react';

/* ═══════════════════════════════════════════════════════════
   TYPES
═══════════════════════════════════════════════════════════ */
type Visibility = 'umum' | 'mitra' | 'keduanya';

interface Agenda {
    id: string;
    user_id?: string | null;
    title: string;
    event_date: string; // YYYY-MM-DD
    description: string;
    location: string;
    registration_link: string;
    visibility: Visibility;
    created_at?: string;
}

const BASE = '/api';

const VIS_LABEL: Record<Visibility, string> = {
    umum: 'Umum',
    mitra: 'Mitra',
    keduanya: 'Umum & Mitra',
};

const VIS_COLOR: Record<Visibility, { bg: string; text: string; border: string }> = {
    umum: { bg: 'rgba(15, 118, 110, 0.1)', text: '#0f766e', border: 'rgba(15, 118, 110, 0.2)' },
    mitra: { bg: 'rgba(147, 51, 234, 0.1)', text: '#9333ea', border: 'rgba(147, 51, 234, 0.2)' },
    keduanya: { bg: 'rgba(225, 29, 72, 0.1)', text: '#e11d48', border: 'rgba(225, 29, 72, 0.2)' },
};

const DAYS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function pad(n: number) {
    return String(n).padStart(2, '0');
}
function toDateStr(y: number, m: number, d: number) {
    return `${y}-${pad(m + 1)}-${pad(d)}`;
}
function formatDisplay(s: string) {
    const [y, m, d] = s.split('-');
    return `${d} ${MONTHS[parseInt(m) - 1]} ${y}`;
}
function getDaysInMonth(y: number, m: number) {
    return new Date(y, m + 1, 0).getDate();
}
function getFirstDOW(y: number, m: number) {
    return new Date(y, m, 1).getDay();
}

/* ═══════════════════════════════════════════════════════════
   TOAST
═══════════════════════════════════════════════════════════ */
function Toast({ msg, type }: { msg: string; type: 'ok' | 'err' }) {
    return createPortal(
        <div
            className={`fixed bottom-6 right-6 z-[9999] py-3 px-5 rounded-xl text-[13.5px] font-bold text-white flex items-center gap-2.5 shadow-xl animate-[fadeIn_0.2s_ease-out] ${type === 'ok' ? 'bg-teal-700' : 'bg-red-600'}`}
        >
            {type === 'ok' ? <CheckCircle2 size={16} /> : <X size={16} />}
            {msg}
        </div>,
        document.body
    );
}

/* ═══════════════════════════════════════════════════════════
   FORM STATE
═══════════════════════════════════════════════════════════ */
interface FormState {
    title: string;
    event_date: string;
    description: string;
    location: string;
    registration_link: string;
    visibility: Visibility;
}

const emptyForm = (date = ''): FormState => ({
    title: '',
    event_date: date,
    description: '',
    location: '',
    registration_link: '',
    visibility: 'umum',
});

/* ═══════════════════════════════════════════════════════════
   ADD / EDIT MODAL
═══════════════════════════════════════════════════════════ */
function AgendaModal({ init, onClose, onSave }: { init: FormState & { id?: string }; onClose: () => void; onSave: (f: FormState) => Promise<void> }) {
    const [f, setF] = useState<FormState>(init);
    const [e, setE] = useState<Partial<Record<keyof FormState, string>>>({});
    const [busy, setBusy] = useState(false);
    const isEdit = Boolean(init.id);

    const upd = (k: keyof FormState) => (ev: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        setF((p) => ({ ...p, [k]: ev.target.value }));
        setE((p) => ({ ...p, [k]: '' }));
    };

    const validate = () => {
        const err: typeof e = {};
        if (!f.title.trim()) err.title = 'Judul wajib diisi.';
        if (!f.event_date) err.event_date = 'Tanggal wajib diisi.';
        if (f.registration_link && !/^https?:\/\/.+/.test(f.registration_link)) err.registration_link = 'URL tidak valid (harus diawali http/https).';
        setE(err);
        return !Object.keys(err).length;
    };

    const submit = async () => {
        if (!validate()) return;
        setBusy(true);
        try {
            await onSave(f);
        } finally {
            setBusy(false);
        }
    };

    return createPortal(
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4">
            {/* Backdrop */}
            <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity animate-[fadeIn_0.2s_ease-out]" onClick={onClose} />

            {/* Modal Box */}
            <div className="relative w-full max-w-[540px] bg-white rounded-[24px] shadow-2xl flex flex-col max-h-[90vh] overflow-hidden animate-[slideUp_0.3s_ease-out]">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-5 border-b border-slate-100 bg-white">
                    <div className="flex items-center gap-3">
                        <h3 className="text-[17px] font-extrabold text-slate-900 leading-none">{isEdit ? 'Edit Agenda' : 'Tambah Agenda'}</h3>
                        {f.event_date && <span className="bg-teal-50 text-teal-700 border border-teal-200 px-2.5 py-1 rounded-md text-[11px] font-bold">{formatDisplay(f.event_date)}</span>}
                    </div>
                    <button
                        className="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-rose-100 hover:text-rose-600 transition-colors focus:outline-none"
                        onClick={onClose}
                    >
                        <X size={16} strokeWidth={2.5} />
                    </button>
                </div>

                {/* Body (Scrollable) */}
                <div className="flex-1 overflow-y-auto px-6 py-5 flex flex-col gap-4">
                    {/* Judul */}
                    <div>
                        <label className="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">Judul Agenda</label>
                        <input
                            className={`w-full h-11 px-4 rounded-xl text-[13px] font-medium bg-slate-50 border transition-all outline-none focus:bg-white focus:ring-2 ${e.title ? 'border-rose-500 bg-rose-50 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-200 text-slate-900 focus:border-teal-600 focus:ring-teal-600/15'}`}
                            placeholder="Contoh: Workshop Manajemen Sekolah"
                            value={f.title}
                            onChange={upd('title')}
                        />
                        {e.title && <span className="text-[11px] text-rose-500 font-bold mt-1 block">{e.title}</span>}
                    </div>

                    {/* Tanggal & Lokasi */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">Tanggal</label>
                            <input
                                className={`w-full h-11 px-4 rounded-xl text-[13px] font-medium bg-slate-50 border transition-all outline-none focus:bg-white focus:ring-2 ${e.event_date ? 'border-rose-500 bg-rose-50 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-200 text-slate-900 focus:border-teal-600 focus:ring-teal-600/15'}`}
                                type="date"
                                value={f.event_date}
                                onChange={upd('event_date')}
                            />
                            {e.event_date && <span className="text-[11px] text-rose-500 font-bold mt-1 block">{e.event_date}</span>}
                        </div>
                        <div>
                            <label className="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">
                                Lokasi <span className="font-medium normal-case text-slate-400 ml-1">(Opsional)</span>
                            </label>
                            <input
                                className="w-full h-11 px-4 rounded-xl text-[13px] font-medium bg-slate-50 border border-slate-200 text-slate-900 transition-all outline-none focus:bg-white focus:border-teal-600 focus:ring-2 focus:ring-teal-600/15"
                                placeholder="Contoh: Aula Gedung A"
                                value={f.location}
                                onChange={upd('location')}
                            />
                        </div>
                    </div>

                    {/* Deskripsi */}
                    <div>
                        <label className="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">
                            Deskripsi <span className="font-medium normal-case text-slate-400 ml-1">(Opsional)</span>
                        </label>
                        <textarea
                            className="w-full p-4 rounded-xl text-[13px] font-medium bg-slate-50 border border-slate-200 text-slate-900 transition-all outline-none focus:bg-white focus:border-teal-600 focus:ring-2 focus:ring-teal-600/15 min-h-[90px] resize-y"
                            placeholder="Keterangan tambahan agenda..."
                            value={f.description}
                            onChange={upd('description')}
                        />
                    </div>

                    {/* Link Pendaftaran */}
                    <div>
                        <label className="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">
                            Link Pendaftaran <span className="font-medium normal-case text-slate-400 ml-1">(Opsional)</span>
                        </label>
                        <input
                            className={`w-full h-11 px-4 rounded-xl text-[13px] font-medium bg-slate-50 border transition-all outline-none focus:bg-white focus:ring-2 ${e.registration_link ? 'border-rose-500 bg-rose-50 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-200 text-slate-900 focus:border-teal-600 focus:ring-teal-600/15'}`}
                            placeholder="https://forms.gle/..."
                            value={f.registration_link}
                            onChange={upd('registration_link')}
                        />
                        {e.registration_link && <span className="text-[11px] text-rose-500 font-bold mt-1 block">{e.registration_link}</span>}
                    </div>

                    {/* Visibility */}
                    <div>
                        <label className="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">Tampilkan Untuk</label>
                        <div className="flex gap-2">
                            {(['umum', 'mitra', 'keduanya'] as Visibility[]).map((v) => {
                                const active = f.visibility === v;
                                let colors = 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50 hover:border-slate-300';
                                if (active) {
                                    if (v === 'umum') colors = 'bg-teal-600 text-white border-teal-600 shadow-md shadow-teal-600/20';
                                    if (v === 'mitra') colors = 'bg-purple-600 text-white border-purple-600 shadow-md shadow-purple-600/20';
                                    if (v === 'keduanya') colors = 'bg-rose-600 text-white border-rose-600 shadow-md shadow-rose-600/20';
                                }
                                return (
                                    <button
                                        type="button"
                                        key={v}
                                        className={`flex-1 py-2.5 rounded-xl text-[12px] font-bold border transition-all focus:outline-none ${colors}`}
                                        onClick={() => setF((p) => ({ ...p, visibility: v }))}
                                    >
                                        {VIS_LABEL[v]}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5 rounded-b-[24px]">
                    <button
                        className="px-5 h-10 rounded-xl text-[13px] font-bold text-slate-600 bg-white border border-slate-200 cursor-pointer transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none"
                        onClick={onClose}
                    >
                        Batal
                    </button>
                    <button
                        className="px-6 h-10 rounded-xl text-[13px] font-bold text-white bg-teal-600 cursor-pointer border-none flex items-center justify-center gap-2 transition-all hover:bg-teal-700 shadow-md shadow-teal-600/20 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none"
                        onClick={submit}
                        disabled={busy}
                    >
                        {busy ? (
                            <>
                                <Loader2 size={16} className="animate-spin" /> Menyimpan...
                            </>
                        ) : (
                            <>
                                <CheckCircle2 size={16} /> {isEdit ? 'Simpan Perubahan' : 'Tambah Agenda'}
                            </>
                        )}
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}

/* ═══════════════════════════════════════════════════════════
   DELETE MODAL
═══════════════════════════════════════════════════════════ */
function DeleteModal({ agenda, onClose, onConfirm }: { agenda: Agenda; onClose: () => void; onConfirm: () => Promise<void> }) {
    const [busy, setBusy] = useState(false);
    const go = async () => {
        setBusy(true);
        try {
            await onConfirm();
        } finally {
            setBusy(false);
        }
    };

    return createPortal(
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity animate-[fadeIn_0.2s_ease-out]" onClick={onClose} />
            <div className="relative w-full max-w-[400px] bg-white border border-slate-100 rounded-[24px] shadow-2xl animate-[slideUp_0.3s_ease-out] overflow-hidden">
                <div className="pt-8 px-6 pb-5 flex flex-col items-center gap-3 text-center">
                    <div className="w-14 h-14 rounded-[16px] bg-rose-100 flex items-center justify-center text-rose-600 shadow-inner">
                        <Trash2 size={26} strokeWidth={2.5} />
                    </div>
                    <div className="text-[18px] font-extrabold text-slate-900 mt-1">Hapus Agenda?</div>
                    <div className="text-[13.5px] text-slate-500 leading-relaxed px-2">
                        Data <b>"{agenda.title}"</b> pada <b>{formatDisplay(agenda.event_date)}</b> akan dihapus secara permanen.
                    </div>
                </div>
                <div className="flex items-center gap-2.5 px-6 pb-6">
                    <button
                        className="flex-1 h-11 rounded-xl text-[13px] font-bold text-slate-600 bg-slate-100 border border-slate-200 cursor-pointer transition-colors hover:bg-slate-200 hover:text-slate-900 focus:outline-none"
                        onClick={onClose}
                    >
                        Batal
                    </button>
                    <button
                        className="flex-1 h-11 rounded-xl text-[13px] font-bold text-white bg-rose-600 cursor-pointer border-none flex items-center justify-center gap-2 transition-all hover:bg-rose-700 shadow-md shadow-rose-600/20 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none"
                        onClick={go}
                        disabled={busy}
                    >
                        {busy ? (
                            <>
                                <Loader2 size={16} className="animate-spin" /> Menghapus...
                            </>
                        ) : (
                            <>
                                <Trash2 size={16} /> Hapus
                            </>
                        )}
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}

/* ═══════════════════════════════════════════════════════════
   MAIN
═══════════════════════════════════════════════════════════ */
export default function AgendaPage() {
    const today = new Date();
    const [year, setYear] = useState(today.getFullYear());
    const [month, setMonth] = useState(today.getMonth());

    const [agendas, setAgendas] = useState<Agenda[]>([]);
    const [loading, setLoading] = useState(true);
    const [selected, setSelected] = useState(toDateStr(today.getFullYear(), today.getMonth(), today.getDate()));

    const [addModal, setAddModal] = useState<FormState | null>(null);
    const [editModal, setEditModal] = useState<(FormState & { id: string }) | null>(null);
    const [delModal, setDelModal] = useState<Agenda | null>(null);
    const [toast, setToast] = useState<{ msg: string; type: 'ok' | 'err' } | null>(null);

    const showToast = (msg: string, type: 'ok' | 'err' = 'ok') => {
        setToast({ msg, type });
        setTimeout(() => setToast(null), 3000);
    };

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch(`${BASE}/agenda?year=${year}&month=${month + 1}`);
            const data = await res.json();
            if (data.success) setAgendas(data.data);
        } catch {
            showToast('Gagal memuat agenda.', 'err');
        } finally {
            setLoading(false);
        }
    }, [year, month]);

    useEffect(() => {
        load();
    }, [load]);

    /* calendar grid */
    const daysInMonth = getDaysInMonth(year, month);
    const firstDay = getFirstDOW(year, month);
    const prevDays = getDaysInMonth(year, month - 1);

    const cells: { date: string; day: number; cur: boolean }[] = [];
    for (let i = firstDay - 1; i >= 0; i--) {
        const d = prevDays - i,
            m = month === 0 ? 11 : month - 1,
            y = month === 0 ? year - 1 : year;
        cells.push({ date: toDateStr(y, m, d), day: d, cur: false });
    }
    for (let d = 1; d <= daysInMonth; d++) cells.push({ date: toDateStr(year, month, d), day: d, cur: true });
    const rem = 7 - (cells.length % 7);
    if (rem < 7)
        for (let d = 1; d <= rem; d++) {
            const m = month === 11 ? 0 : month + 1,
                y = month === 11 ? year + 1 : year;
            cells.push({ date: toDateStr(y, m, d), day: d, cur: false });
        }

    const byDate: Record<string, Agenda[]> = {};
    agendas.forEach((a) => {
        if (!byDate[a.event_date]) byDate[a.event_date] = [];
        byDate[a.event_date].push(a);
    });

    const todayStr = toDateStr(today.getFullYear(), today.getMonth(), today.getDate());
    const selAgendas = byDate[selected] ?? [];

    const prevMonth = () => {
        if (month === 0) {
            setYear((y) => y - 1);
            setMonth(11);
        } else setMonth((m) => m - 1);
    };
    const nextMonth = () => {
        if (month === 11) {
            setYear((y) => y + 1);
            setMonth(0);
        } else setMonth((m) => m + 1);
    };
    const goToday = () => {
        setYear(today.getFullYear());
        setMonth(today.getMonth());
        setSelected(todayStr);
    };

    /* CRUD */
    const handleAdd = async (f: FormState) => {
        try {
            const res = await fetch(`${BASE}/agenda`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(f),
            });
            const data = await res.json();
            if (data.success) {
                showToast('Agenda berhasil ditambahkan.', 'ok');
                setAddModal(null);
                await load();
            } else if (data.errors) {
                const firstErr = Object.values(data.errors as Record<string, string[]>)[0]?.[0];
                showToast(firstErr ?? 'Validasi gagal.', 'err');
            } else {
                showToast(data.message ?? 'Gagal menyimpan.', 'err');
            }
        } catch {
            showToast('Terjadi kesalahan koneksi saat menyimpan agenda.', 'err');
        }
    };

    const handleEdit = async (f: FormState) => {
        if (!editModal?.id) return;
        try {
            const res = await fetch(`${BASE}/agenda/${editModal.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(f),
            });
            const data = await res.json();
            if (data.success) {
                showToast('Agenda berhasil diperbarui.', 'ok');
                setEditModal(null);
                await load();
            } else if (data.errors) {
                const firstErr = Object.values(data.errors as Record<string, string[]>)[0]?.[0];
                showToast(firstErr ?? 'Validasi gagal.', 'err');
            } else {
                showToast(data.message ?? 'Gagal memperbarui.', 'err');
            }
        } catch {
            showToast('Terjadi kesalahan koneksi saat memperbarui agenda.', 'err');
        }
    };

    const handleDelete = async () => {
        if (!delModal?.id) return;
        try {
            const res = await fetch(`${BASE}/agenda/${delModal.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                },
            });
            const data = await res.json();
            if (data.success) {
                showToast('Agenda berhasil dihapus.', 'ok');
                setDelModal(null);
                await load();
            } else {
                showToast(data.message ?? 'Gagal menghapus.', 'err');
            }
        } catch {
            showToast('Terjadi kesalahan koneksi saat menghapus agenda.', 'err');
        }
    };

    return (
        <>
            <style>{`
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes slideUp { from { transform: translateY(15px) scale(0.98); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
            `}</style>

            <div className="flex flex-col gap-6">
                {/* Header Page */}
                <div>
                    <h1 className="text-[22px] font-extrabold text-slate-900 leading-tight">Agenda Kegiatan</h1>
                    <p className="text-[12.5px] font-medium text-slate-500 mt-1">Kelola jadwal dan agenda operasional institusi QLC</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start">
                    {/* ── Kalender Utama ── */}
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                        {/* Navigasi Bulan */}
                        <div className="flex items-center justify-between p-5 border-b border-slate-200">
                            <button
                                className="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-50 border border-slate-200 text-slate-600 cursor-pointer transition-colors hover:bg-teal-600 hover:text-white hover:border-teal-600 focus:outline-none"
                                onClick={prevMonth}
                            >
                                <ChevronLeft size={18} />
                            </button>
                            <div className="flex items-center gap-3">
                                <h2 className="text-[18px] font-extrabold text-slate-900 flex items-center gap-2.5">
                                    {MONTHS[month]}
                                    <span className="text-[13px] font-bold text-teal-700 bg-teal-50 px-3 py-0.5 rounded-full border border-teal-100 shadow-sm">{year}</span>
                                </h2>
                                <button
                                    className="px-4 h-9 rounded-lg text-[12px] font-bold bg-slate-100 text-slate-600 border border-slate-200 cursor-pointer transition-colors hover:bg-slate-200 hover:text-slate-900 focus:outline-none ml-2"
                                    onClick={goToday}
                                >
                                    Hari Ini
                                </button>
                            </div>
                            <button
                                className="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-50 border border-slate-200 text-slate-600 cursor-pointer transition-colors hover:bg-teal-600 hover:text-white hover:border-teal-600 focus:outline-none"
                                onClick={nextMonth}
                            >
                                <ChevronRight size={18} />
                            </button>
                        </div>

                        {/* Label Hari */}
                        <div className="grid grid-cols-7 border-b border-slate-200 bg-slate-50/80">
                            {DAYS.map((d, i) => (
                                <div
                                    key={d}
                                    className={`py-3.5 text-center text-[10.5px] font-extrabold uppercase tracking-widest ${i === 0 ? 'text-rose-600' : i === 6 ? 'text-blue-600' : 'text-slate-500'}`}
                                >
                                    {d}
                                </div>
                            ))}
                        </div>

                        {/* Grid Tanggal */}
                        {loading ? (
                            <div className="py-20 flex justify-center">
                                <Loader2 size={32} className="text-teal-600 animate-spin" />
                            </div>
                        ) : (
                            <div className="grid grid-cols-7 bg-slate-200 gap-px border-b border-slate-200">
                                {cells.map((cell, idx) => {
                                    const dow = idx % 7;
                                    const isToday = cell.date === todayStr;
                                    const isSel = cell.date === selected;
                                    const items = byDate[cell.date] ?? [];

                                    // Set background logic
                                    let bgCls = cell.cur ? 'bg-white' : 'bg-slate-50/60';
                                    if (isSel && !isToday) bgCls = 'bg-teal-50/50';
                                    if (isToday) bgCls = 'bg-teal-50';

                                    // Set number color logic
                                    let numCls = 'w-[28px] h-[28px] rounded-[10px] flex items-center justify-center text-[13px] font-extrabold mb-1.5 ';
                                    if (isToday) numCls += 'bg-teal-600 text-white shadow-md shadow-teal-600/20';
                                    else if (!cell.cur) numCls += 'text-slate-400';
                                    else if (dow === 0) numCls += 'text-rose-600';
                                    else if (dow === 6) numCls += 'text-blue-600';
                                    else numCls += 'text-slate-700';

                                    return (
                                        <div
                                            key={cell.date}
                                            className={`min-h-[90px] lg:min-h-[115px] p-1.5 md:p-2 transition-colors cursor-pointer relative group ${bgCls}`}
                                            onClick={() => {
                                                setSelected(cell.date);
                                                setAddModal(emptyForm(cell.date));
                                            }}
                                        >
                                            {/* Ring Indikator Terpilih */}
                                            {isSel && !isToday && <div className="absolute inset-0 ring-2 ring-inset ring-teal-500/30 z-10 pointer-events-none" />}

                                            <div className="relative z-20">
                                                <div className={numCls}>{cell.day}</div>

                                                <div className="flex flex-col gap-1">
                                                    {items.slice(0, 2).map((a) => {
                                                        const vc = VIS_COLOR[a.visibility];
                                                        return (
                                                            <div
                                                                key={a.id}
                                                                className="flex items-center px-1.5 py-0.5 rounded-md text-[9px] md:text-[10.5px] font-bold leading-snug cursor-pointer transition-all hover:brightness-95 whitespace-nowrap overflow-hidden text-ellipsis w-full"
                                                                style={{ background: vc.bg, color: vc.text, border: `1px solid ${vc.border}` }}
                                                                onClick={(ev) => {
                                                                    ev.stopPropagation();
                                                                    setSelected(cell.date);
                                                                }}
                                                            >
                                                                {a.title}
                                                            </div>
                                                        );
                                                    })}
                                                    {items.length > 2 && (
                                                        <span className="text-[10px] font-extrabold text-slate-500 px-1.5 py-px bg-slate-100 rounded-md mt-px self-start">
                                                            +{items.length - 2} lagi
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {/* Legenda */}
                        <div className="flex items-center gap-4 flex-wrap py-4 px-5 bg-white">
                            {(Object.entries(VIS_COLOR) as [Visibility, (typeof VIS_COLOR)[Visibility]][]).map(([k, v]) => (
                                <div key={k} className="flex items-center gap-1.5 text-[11.5px] font-bold text-slate-500">
                                    <div className="w-2.5 h-2.5 rounded-[3px] shrink-0" style={{ background: v.text }} />
                                    {VIS_LABEL[k]}
                                </div>
                            ))}
                            <div className="flex items-center gap-1.5 text-[11.5px] font-bold text-slate-400 ml-auto">
                                <Calendar size={12} /> Klik area tanggal untuk tambah agenda
                            </div>
                        </div>
                    </div>

                    {/* ── Side panel (Daftar Agenda Hari yang Dipilih) ── */}
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-full max-h-[800px]">
                        <div className="p-5 border-b border-slate-200 bg-slate-50/50">
                            <div className="text-[14px] font-extrabold text-slate-900">{selected ? formatDisplay(selected) : 'Pilih Tanggal'}</div>
                            <div className="text-[11.5px] font-semibold text-slate-500 mt-1">{selAgendas.length ? `${selAgendas.length} Agenda Tersedia` : 'Belum ada agenda'}</div>
                        </div>

                        <div className="flex-1 overflow-y-auto p-4">
                            {selAgendas.length === 0 ? (
                                <div className="py-12 flex flex-col items-center justify-center text-center text-slate-400">
                                    <div className="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center mb-3 border border-slate-100">
                                        <Calendar size={28} className="text-slate-300" />
                                    </div>
                                    <div className="text-[13px] font-bold text-slate-500">Kosong</div>
                                    <div className="text-[11.5px] mt-1 px-4">Tidak ada agenda pada tanggal ini.</div>
                                </div>
                            ) : (
                                <div className="flex flex-col gap-3">
                                    {selAgendas.map((a) => {
                                        const vc = VIS_COLOR[a.visibility];
                                        return (
                                            <div
                                                key={a.id}
                                                className="p-3.5 rounded-xl bg-white border border-slate-200 shadow-sm transition-all hover:shadow-md hover:border-slate-300 relative group"
                                            >
                                                <div className="text-[13px] font-extrabold text-slate-900 mb-2 leading-tight pr-6">{a.title}</div>

                                                {a.location && (
                                                    <div className="flex items-start gap-1.5 text-[11px] text-slate-600 font-medium mb-1.5">
                                                        <MapPin size={12} className="shrink-0 mt-0.5 text-slate-400" />
                                                        <span className="leading-snug">{a.location}</span>
                                                    </div>
                                                )}
                                                {a.registration_link && (
                                                    <div className="flex items-center gap-1.5 text-[11px] text-slate-600 font-medium mb-1.5">
                                                        <Link size={12} className="shrink-0 text-slate-400" />
                                                        <a
                                                            href={a.registration_link}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="text-blue-600 underline font-bold"
                                                            onClick={(ev) => ev.stopPropagation()}
                                                        >
                                                            Buka Link Pendaftaran
                                                        </a>
                                                    </div>
                                                )}
                                                {a.description && (
                                                    <div className="text-[11.5px] text-slate-500 mt-2 leading-relaxed bg-slate-50 p-2 rounded-lg border border-slate-100">{a.description}</div>
                                                )}

                                                <div className="mt-3 flex items-center justify-between">
                                                    <span
                                                        className="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-extrabold uppercase tracking-wide border"
                                                        style={{ background: vc.bg, color: vc.text, borderColor: vc.border }}
                                                    >
                                                        <Eye size={10} strokeWidth={2.5} />
                                                        {VIS_LABEL[a.visibility]}
                                                    </span>
                                                </div>

                                                {/* Action Buttons (Absolute top right) */}
                                                <div className="absolute top-3 right-3 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button
                                                        className="w-7 h-7 rounded-lg flex items-center justify-center bg-white border border-slate-200 text-blue-600 shadow-sm transition-colors hover:bg-blue-50 hover:border-blue-200"
                                                        onClick={() => setEditModal({ ...emptyForm(a.event_date), ...a, id: a.id })}
                                                    >
                                                        <Pencil size={12} strokeWidth={2.5} />
                                                    </button>
                                                    <button
                                                        className="w-7 h-7 rounded-lg flex items-center justify-center bg-white border border-slate-200 text-red-600 shadow-sm transition-colors hover:bg-red-50 hover:border-red-200"
                                                        onClick={() => setDelModal(a)}
                                                    >
                                                        <Trash2 size={12} strokeWidth={2.5} />
                                                    </button>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        <div className="p-4 border-t border-slate-200 bg-slate-50">
                            <button
                                className="w-full h-11 rounded-xl text-[13px] font-extrabold text-white bg-teal-600 cursor-pointer border-none flex items-center justify-center gap-2 transition-all hover:bg-teal-700 shadow-md shadow-teal-600/20 focus:outline-none"
                                onClick={() => setAddModal(emptyForm(selected))}
                            >
                                <Plus size={16} strokeWidth={2.5} /> Tambah Agenda
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {addModal && <AgendaModal init={addModal} onClose={() => setAddModal(null)} onSave={handleAdd} />}
            {editModal && <AgendaModal init={editModal} onClose={() => setEditModal(null)} onSave={handleEdit} />}
            {delModal && <DeleteModal agenda={delModal} onClose={() => setDelModal(null)} onConfirm={handleDelete} />}
            {toast && <Toast msg={toast.msg} type={toast.type} />}
        </>
    );
}
