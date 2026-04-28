<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const expenseList = ref([...(props.data.expenses ?? [])]);
const bookingOptions = computed(() => props.data.bookingOptions ?? []);
const vendorOptions = computed(() => props.data.vendorOptions ?? []);
const expenseCategoryOptions = computed(() => props.data.expenseCategoryOptions ?? []);
const userOptions = computed(() => props.data.userOptions ?? []);
const search = ref('');
const requestId = ref(0);
const loading = ref(false);
const showModal = ref(false);
const editingExpenseId = ref(null);
const uploadInput = ref(null);
const cameraInput = ref(null);
const receiptFile = ref(null);
const receiptPreviewUrl = ref('');
const showCameraCapture = ref(false);
const cameraVideo = ref(null);
const cameraCanvas = ref(null);
const cameraError = ref('');
const expenseToDelete = ref(null);
const showDeleteConfirm = ref(false);
let cameraStream = null;
const {
    saving,
    fieldErrors,
    submitForm,
    deleteRecord,
} = useWorkspaceCrud();
const clientErrors = ref({});
const form = ref(buildExpenseForm());

function buildExpenseForm(expense = null) {
    return {
        expense_name: expense?.expense_name ?? '',
        expense_date: expense?.expense_date ?? new Date().toISOString().slice(0, 10),
        amount: expense?.amount ?? '',
        booking_id: expense?.booking_id ? String(expense.booking_id) : '',
        vendor_id: expense?.vendor_id ? String(expense.vendor_id) : '',
        expense_category_id: expense?.expense_category_id ? String(expense.expense_category_id) : '',
        user_id: expense?.user_id ? String(expense.user_id) : '',
        notes: expense?.notes ?? '',
        remove_receipt: false,
    };
}

const expenses = computed(() => expenseList.value);
const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));
const editingExpense = computed(() => expenses.value.find((entry) => entry.id === editingExpenseId.value) ?? null);
const selectedReceiptName = computed(() => receiptFile.value?.name ?? editingExpense.value?.receipt_name ?? '');
const liveCameraSupported = computed(() => (
    typeof navigator !== 'undefined'
    && !!navigator.mediaDevices
    && typeof navigator.mediaDevices.getUserMedia === 'function'
));

const sortExpenses = (records) => [...records].sort((left, right) => {
    const leftDate = String(left.expense_date ?? '');
    const rightDate = String(right.expense_date ?? '');

    if (leftDate !== rightDate) {
        return rightDate.localeCompare(leftDate);
    }

    return Number(right.id ?? 0) - Number(left.id ?? 0);
});

const clearSelectedReceipt = () => {
    if (receiptPreviewUrl.value) {
        URL.revokeObjectURL(receiptPreviewUrl.value);
        receiptPreviewUrl.value = '';
    }

    receiptFile.value = null;

    if (uploadInput.value) {
        uploadInput.value.value = '';
    }

    if (cameraInput.value) {
        cameraInput.value.value = '';
    }
};

const stopCameraStream = () => {
    if (cameraStream) {
        cameraStream.getTracks().forEach((track) => track.stop());
        cameraStream = null;
    }

    if (cameraVideo.value) {
        cameraVideo.value.srcObject = null;
    }
};

const setSelectedReceiptFile = (file) => {
    clearSelectedReceipt();
    receiptFile.value = file;
    form.value.remove_receipt = false;

    if (file.type.startsWith('image/')) {
        receiptPreviewUrl.value = URL.createObjectURL(file);
    }
};

const handleReceiptSelection = (event) => {
    const file = event.target?.files?.[0];

    if (!file) {
        return;
    }

    setSelectedReceiptFile(file);
};

const openCameraCapture = async () => {
    if (!liveCameraSupported.value) {
        cameraInput.value?.click();
        return;
    }

    stopCameraStream();
    cameraError.value = '';
    showCameraCapture.value = true;

    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' },
            },
            audio: false,
        });

        await nextTick();

        if (cameraVideo.value) {
            cameraVideo.value.srcObject = cameraStream;
            await cameraVideo.value.play();
        }
    } catch {
        cameraError.value = 'Camera access was blocked or unavailable. You can still upload the receipt file.';
        stopCameraStream();
    }
};

const closeCameraCapture = () => {
    showCameraCapture.value = false;
    cameraError.value = '';
    stopCameraStream();
};

const captureCameraPhoto = () => {
    if (!cameraVideo.value || !cameraCanvas.value) {
        return;
    }

    const video = cameraVideo.value;
    const canvas = cameraCanvas.value;
    const width = video.videoWidth || 1280;
    const height = video.videoHeight || 720;

    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');

    if (!context) {
        cameraError.value = 'Could not capture the camera image.';
        return;
    }

    context.drawImage(video, 0, 0, width, height);
    canvas.toBlob((blob) => {
        if (!blob) {
            cameraError.value = 'Could not capture the camera image.';
            return;
        }

        const file = new File([blob], `receipt-${Date.now()}.jpg`, { type: 'image/jpeg' });
        setSelectedReceiptFile(file);
        closeCameraCapture();
    }, 'image/jpeg', 0.92);
};

const openCreateModal = () => {
    editingExpenseId.value = null;
    clientErrors.value = {};
    form.value = buildExpenseForm();
    clearSelectedReceipt();
    closeCameraCapture();
    showModal.value = true;
};

const openEditModal = (expense) => {
    editingExpenseId.value = expense.id;
    clientErrors.value = {};
    form.value = buildExpenseForm(expense);
    clearSelectedReceipt();
    closeCameraCapture();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingExpenseId.value = null;
    clientErrors.value = {};
    form.value = buildExpenseForm();
    clearSelectedReceipt();
    closeCameraCapture();
};

const fetchExpenses = async () => {
    const currentRequest = ++requestId.value;
    loading.value = true;

    try {
        const params = new URLSearchParams({
            search: search.value.trim(),
        });

        const response = await fetch(`${props.data.routes.expenses}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok || currentRequest !== requestId.value) {
            return;
        }

        const payload = await response.json();
        expenseList.value = sortExpenses(payload.records ?? []);
    } finally {
        if (currentRequest === requestId.value) {
            loading.value = false;
        }
    }
};

watch(search, () => {
    fetchExpenses();
});

watch(() => form.value.remove_receipt, (value) => {
    if (value) {
        clearSelectedReceipt();
    }
});

const saveExpense = async () => {
    const errors = {};

    if (isBlank(form.value.expense_name)) {
        errors.expense_name = requiredMessage('Expense name');
    }

    if (isBlank(form.value.expense_date)) {
        errors.expense_date = requiredMessage('Expense date');
    }

    if (isBlank(form.value.amount)) {
        errors.amount = requiredMessage('Amount');
    }

    clientErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    const editingRecord = editingExpense.value;
    const formData = new FormData();
    formData.append('expense_name', form.value.expense_name ?? '');
    formData.append('expense_date', form.value.expense_date ?? '');
    formData.append('amount', form.value.amount ?? '');
    formData.append('booking_id', form.value.booking_id ?? '');
    formData.append('vendor_id', form.value.vendor_id ?? '');
    formData.append('expense_category_id', form.value.expense_category_id ?? '');
    formData.append('user_id', form.value.user_id ?? '');
    formData.append('notes', form.value.notes ?? '');
    formData.append('remove_receipt', form.value.remove_receipt ? '1' : '0');

    if (receiptFile.value) {
        formData.append('receipt', receiptFile.value);
    }

    if (editingRecord) {
        formData.append('_method', 'PUT');
    }

    try {
        const record = await submitForm({
            url: editingRecord?.update_url ?? props.data.routes.store,
            method: 'post',
            data: formData,
        });

        const index = expenseList.value.findIndex((entry) => entry.id === record.id);
        expenseList.value = sortExpenses(index >= 0
            ? expenseList.value.map((entry) => entry.id === record.id ? record : entry)
            : [...expenseList.value, record]);
        closeModal();
    } catch {}
};

const askRemoveExpense = (expense) => {
    expenseToDelete.value = expense;
    showDeleteConfirm.value = true;
};

const cancelRemoveExpense = () => {
    expenseToDelete.value = null;
    showDeleteConfirm.value = false;
};

const removeExpense = async () => {
    const expense = expenseToDelete.value;

    if (!expense?.delete_url) {
        return;
    }

    try {
        await deleteRecord({ url: expense.delete_url });
        expenseList.value = expenseList.value.filter((entry) => entry.id !== expense.id);

        if (editingExpenseId.value === expense.id) {
            closeModal();
        }

        cancelRemoveExpense();
    } catch {}
};

onBeforeUnmount(() => {
    stopCameraStream();
    clearSelectedReceipt();
});
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-amber-200">Expenses Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Track booking and business expenses</h2>
        <p class="text-sm text-stone-300">
            Capture receipts, link costs to bookings or vendors, and keep standalone expenses visible too.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-amber-200">Expenses</p>
                <h3 class="mt-1 text-sm font-semibold italic">Manage records and receipts</h3>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ expenses.length }}</span>
                <button type="button" class="rounded-xl bg-amber-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-200" @click="openCreateModal">
                    Add Expense
                </button>
            </div>
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_16rem]">
            <div class="rounded-2xl border border-white/10 bg-slate-950/40 px-3 py-2.5 text-sm text-stone-400">
                Link an expense to a booking, a vendor, both, or neither.
            </div>
            <input v-model="search" type="text" placeholder="Search expenses" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50">
        </div>

        <div class="overflow-hidden rounded-2xl border border-white/10">
            <div class="grid grid-cols-[4.5rem_8rem_minmax(0,1.1fr)_10rem_minmax(0,1fr)_minmax(0,1fr)_8rem_8rem_7rem_7rem] gap-3 bg-white/[0.03] px-3 py-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                <span>ID</span>
                <span>Date</span>
                <span>Expense</span>
                <span>Category</span>
                <span>Booking</span>
                <span>Vendor</span>
                <span>Amount</span>
                <span>Receipt</span>
                <span>Edit</span>
                <span>Delete</span>
            </div>
            <div v-for="expense in expenses" :key="expense.id" class="grid grid-cols-[4.5rem_8rem_minmax(0,1.1fr)_10rem_minmax(0,1fr)_minmax(0,1fr)_8rem_8rem_7rem_7rem] items-center gap-3 border-t border-white/10 px-3 py-2.5">
                <p class="truncate text-sm text-stone-300">{{ expense.id }}</p>
                <p class="text-sm text-stone-300">{{ expense.expense_date_label }}</p>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ expense.expense_name }}</p>
                    <p v-if="expense.notes" class="mt-1 truncate text-xs text-stone-500">{{ expense.notes }}</p>
                </div>
                <p class="truncate text-sm text-stone-300">{{ expense.expense_category_label }}</p>
                <p class="truncate text-sm text-stone-300">{{ expense.booking_label }}</p>
                <p class="truncate text-sm text-stone-300">{{ expense.vendor_label }}</p>
                <p class="text-sm font-semibold text-amber-100">${{ expense.amount }}</p>
                <a v-if="expense.receipt_url" :href="expense.receipt_url" target="_blank" rel="noreferrer" class="truncate text-sm font-medium text-cyan-200 hover:text-cyan-100">View receipt</a>
                <p v-else class="text-sm text-stone-500">No receipt</p>
                <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/5" @click="openEditModal(expense)">Edit</button>
                <button type="button" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/10" @click="askRemoveExpense(expense)">Delete</button>
            </div>
            <div v-if="loading" class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">
                Loading expenses...
            </div>
            <div v-else-if="!expenses.length" class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">
                No expenses recorded yet.
            </div>
        </div>
    </section>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur-sm" @click.self="closeModal">
        <div class="w-full max-w-4xl overflow-hidden rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/40">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-amber-200">{{ editingExpenseId ? 'Edit Expense' : 'New Expense' }}</p>
                    <p class="mt-1 text-sm text-stone-300">Add the cost details and attach a receipt from file upload or camera capture.</p>
                </div>
                <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="closeModal">Close</button>
            </div>

            <form class="max-h-[80vh] overflow-y-auto p-4" novalidate @submit.prevent="saveExpense">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Expense Name</label>
                        <input v-model="form.expense_name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50" :class="firstError(validationErrors, 'expense_name') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(validationErrors, 'expense_name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'expense_name') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Expense Date</label>
                        <input v-model="form.expense_date" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50" :class="firstError(validationErrors, 'expense_date') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(validationErrors, 'expense_date')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'expense_date') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Amount</label>
                        <input v-model="form.amount" type="number" min="0" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50" :class="firstError(validationErrors, 'amount') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(validationErrors, 'amount')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'amount') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Expense Category</label>
                        <select v-model="form.expense_category_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50">
                            <option value="">Not linked</option>
                            <option v-for="category in expenseCategoryOptions" :key="category.id" :value="String(category.id)">{{ category.label }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Booking</label>
                        <select v-model="form.booking_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50">
                            <option value="">Not linked</option>
                            <option v-for="booking in bookingOptions" :key="booking.id" :value="String(booking.id)">{{ booking.label }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Vendor</label>
                        <select v-model="form.vendor_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50">
                            <option value="">Not linked</option>
                            <option v-for="vendor in vendorOptions" :key="vendor.id" :value="String(vendor.id)">{{ vendor.label }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">User</label>
                        <select v-model="form.user_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50">
                            <option value="">Not linked</option>
                            <option v-for="user in userOptions" :key="user.id" :value="String(user.id)">{{ user.label }}</option>
                        </select>
                    </div>
                    <div class="xl:col-span-4">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Notes</label>
                        <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50" />
                    </div>
                    <div class="xl:col-span-4 rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Receipt</p>
                                <p class="mt-1 text-sm text-stone-300">Upload a file or take a receipt photo using your camera.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <input ref="uploadInput" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" class="hidden" @change="handleReceiptSelection">
                                <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="handleReceiptSelection">
                                <button type="button" class="rounded-xl border border-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/5" @click="uploadInput?.click()">Upload receipt</button>
                                <button type="button" class="rounded-xl border border-amber-300/30 px-3 py-2 text-sm font-semibold text-amber-100 transition hover:bg-amber-300/10" @click="openCameraCapture">Take photo</button>
                            </div>
                        </div>
                        <p v-if="selectedReceiptName" class="mt-3 text-sm text-stone-200">{{ selectedReceiptName }}</p>
                        <div v-if="receiptPreviewUrl" class="mt-3 overflow-hidden rounded-xl border border-white/10">
                            <img :src="receiptPreviewUrl" alt="Receipt preview" class="max-h-64 w-full object-contain bg-slate-950">
                        </div>
                        <a v-else-if="editingExpense?.receipt_url && !form.remove_receipt" :href="editingExpense.receipt_url" target="_blank" rel="noreferrer" class="mt-3 inline-flex text-sm font-medium text-cyan-200 hover:text-cyan-100">
                            View current receipt
                        </a>
                        <label v-if="editingExpense?.receipt_url" class="mt-3 flex min-h-[42px] items-center gap-3 rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-200">
                            <input v-model="form.remove_receipt" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-slate-950 text-amber-300">
                            <span>Remove current receipt</span>
                        </label>
                        <p v-if="firstError(validationErrors, 'receipt')" class="mt-2 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'receipt') }}</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2 border-t border-white/10 pt-4">
                    <button type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5" @click="closeModal">Cancel</button>
                    <button type="submit" class="rounded-xl bg-amber-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                        {{ saving ? 'Saving...' : editingExpenseId ? 'Save expense' : 'Add expense' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div v-if="showCameraCapture" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/80 px-4 py-6 backdrop-blur-sm" @click.self="closeCameraCapture">
        <div class="w-full max-w-3xl overflow-hidden rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/50">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-amber-200">Camera Capture</p>
                    <p class="mt-1 text-sm text-stone-300">Take a receipt photo and attach it directly to this expense.</p>
                </div>
                <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="closeCameraCapture">Close</button>
            </div>

            <div class="p-4">
                <p v-if="cameraError" class="rounded-xl border border-rose-300/20 bg-rose-300/10 px-3 py-2 text-sm text-rose-100">{{ cameraError }}</p>
                <div v-else class="overflow-hidden rounded-2xl border border-white/10 bg-slate-950">
                    <video ref="cameraVideo" autoplay playsinline muted class="max-h-[65vh] w-full object-contain" />
                </div>
                <canvas ref="cameraCanvas" class="hidden" />

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5" @click="closeCameraCapture">Cancel</button>
                    <button type="button" class="rounded-xl bg-amber-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="!!cameraError" @click="captureCameraPhoto">
                        Capture Photo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete expense?"
        :message="`Are you sure you want to delete the record ${expenseToDelete?.expense_name || 'this expense'}?`"
        confirm-label="Delete expense"
        :loading="saving"
        @cancel="cancelRemoveExpense"
        @confirm="removeExpense"
    />
</template>
