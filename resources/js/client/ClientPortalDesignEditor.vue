<script setup>
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Layer as VLayer, Rect as VRect, Stage as VStage, Text as VText, Image as VImage, Transformer as VTransformer } from 'vue-konva';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const saving = ref(false);
const message = ref('');
const error = ref('');
const fileInput = ref(null);
const stageRef = ref(null);
const transformerRef = ref(null);
const selectedId = ref(null);
const nodeRefs = new Map();
const imageElements = ref({});
const undoStack = ref([]);
const isRestoringHistory = ref(false);
const zoomScale = ref(0.65);

const defaultDesign = {
    width: 400,
    height: 1200,
    backgroundColor: '#f8fafc',
    nodes: [],
};

const canvasPresets = [
    { label: '2 x 6 in', value: '2x6', width: 400, height: 1200 },
    { label: '4 x 6 in', value: '4x6', width: 800, height: 1200 },
];

const systemFontOptions = [
    { label: 'Helvetica', value: 'Helvetica' },
    { label: 'Georgia', value: 'Georgia' },
    { label: 'Times New Roman', value: 'Times New Roman' },
    { label: 'Courier New', value: 'Courier New' },
    { label: 'Verdana', value: 'Verdana' },
    { label: 'Trebuchet MS', value: 'Trebuchet MS' },
];

const cloneDesign = (design) => JSON.parse(JSON.stringify(design));
const createNodeId = () => {
    if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }

    return `node-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
};

const normalizeNode = (node, index) => ({
    id: node.id ?? `node-${index + 1}`,
    type: node.type ?? 'text',
    x: Number(node.x ?? 120),
    y: Number(node.y ?? 120),
    rotation: Number(node.rotation ?? 0),
    scaleX: Number(node.scaleX ?? 1),
    scaleY: Number(node.scaleY ?? 1),
    width: Number(node.width ?? (node.type === 'image' ? 320 : 220)),
    height: Number(node.height ?? (node.type === 'image' ? 220 : 60)),
    text: node.text ?? 'Edit me',
    fontSize: Number(node.fontSize ?? 42),
    fontFamily: node.fontFamily ?? 'Helvetica',
    fontStyle: node.fontStyle ?? 'normal',
    fill: node.fill ?? '#0f172a',
    src: node.src ?? null,
    templateKind: node.templateKind ?? null,
});

const normalizeDesign = (design) => ({
    width: Number(design?.width ?? defaultDesign.width),
    height: Number(design?.height ?? defaultDesign.height),
    backgroundColor: design?.backgroundColor ?? defaultDesign.backgroundColor,
    remarks: design?.remarks ?? '',
    nodes: Array.isArray(design?.nodes) ? design.nodes.map(normalizeNode) : [],
});

const designState = ref(normalizeDesign(props.data.design?.design_data));
const title = ref(props.data.design?.title ?? 'Client design draft');
const lastSavedLabel = ref(props.data.design?.last_saved_at_label ?? null);
const customFontOptions = computed(() => {
    const families = Array.isArray(props.data.fonts)
        ? [...new Set(props.data.fonts.map((font) => String(font.family ?? '').trim()).filter(Boolean))]
        : [];

    return families.map((family) => ({
        label: `${family} (Brand)`,
        value: family,
    }));
});
const fontOptions = computed(() => {
    const seen = new Set();

    return [...customFontOptions.value, ...systemFontOptions].filter((font) => {
        const key = String(font.value ?? '').toLowerCase();

        if (seen.has(key)) {
            return false;
        }

        seen.add(key);
        return true;
    });
});

const selectedNode = computed(() => designState.value.nodes.find((node) => node.id === selectedId.value) ?? null);
const selectedNodeIndex = computed(() => designState.value.nodes.findIndex((node) => node.id === selectedId.value));
const selectedNodeIsBold = computed(() => (selectedNode.value?.fontStyle ?? '').includes('bold'));
const selectedNodeIsItalic = computed(() => (selectedNode.value?.fontStyle ?? '').includes('italic'));
const activeCanvasPreset = computed(() => canvasPresets.find((preset) => preset.width === designState.value.width && preset.height === designState.value.height)?.value ?? null);
const canUndo = computed(() => undoStack.value.length > 0);
const zoomPercent = computed(() => `${Math.round(zoomScale.value * 100)}%`);

const loadImage = (node) => {
    if (!node?.src || imageElements.value[node.id]) {
        return;
    }

    const image = new window.Image();
    image.crossOrigin = 'anonymous';
    image.onload = () => {
        imageElements.value = {
            ...imageElements.value,
            [node.id]: image,
        };
    };
    image.src = node.src;
};

const resetLoadedImages = (nodes) => {
    const nextImages = {};

    nodes
        .filter((node) => node.type === 'image' && node.src && imageElements.value[node.id])
        .forEach((node) => {
            nextImages[node.id] = imageElements.value[node.id];
        });

    imageElements.value = nextImages;
};

watch(
    () => designState.value.nodes,
    (nodes) => {
        nodes.filter((node) => node.type === 'image').forEach(loadImage);
    },
    { immediate: true, deep: true },
);

const setNodeRef = (id, instance) => {
    if (!instance) {
        nodeRefs.delete(id);
        return;
    }

    nodeRefs.set(id, instance);
};

watch(selectedId, async (id) => {
    await nextTick();

    const transformer = transformerRef.value?.getNode?.();

    if (!transformer) {
        return;
    }

    if (!id || !nodeRefs.has(id)) {
        transformer.nodes([]);
        transformer.getLayer()?.batchDraw();
        return;
    }

    const target = nodeRefs.get(id)?.getNode?.();

    if (!target) {
        transformer.nodes([]);
        transformer.getLayer()?.batchDraw();
        return;
    }

    transformer.nodes([target]);
    transformer.getLayer()?.batchDraw();
});

const redrawStage = () => {
    stageRef.value?.getNode?.()?.batchDraw();
};

const pushHistory = () => {
    if (isRestoringHistory.value) {
        return;
    }

    undoStack.value = [
        ...undoStack.value.slice(-29),
        cloneDesign(designState.value),
    ];
};

const replaceDesignState = (nextDesign, options = {}) => {
    if (options.trackHistory !== false) {
        pushHistory();
    }

    const normalized = normalizeDesign(nextDesign);
    designState.value = normalized;
    resetLoadedImages(normalized.nodes);

    if (selectedId.value && !normalized.nodes.some((node) => node.id === selectedId.value)) {
        selectedId.value = null;
    }
};

const loadConfiguredFonts = async () => {
    if (!Array.isArray(props.data.fonts) || typeof document === 'undefined' || !document.fonts?.load) {
        return;
    }

    await Promise.allSettled(
        props.data.fonts.map((font) => {
            const family = String(font.family ?? '').trim();

            if (family === '') {
                return Promise.resolve();
            }

            const weight = Number(font.weight ?? 400);
            const style = String(font.style ?? 'normal');

            return document.fonts.load(`${style} ${weight} 24px "${family}"`);
        }),
    );

    redrawStage();
};

const updateNode = (id, attributes) => {
    replaceDesignState({
        ...designState.value,
        nodes: designState.value.nodes.map((node) => node.id === id ? { ...node, ...attributes } : node),
    });
};

const removeNodeById = (id) => {
    replaceDesignState({
        ...designState.value,
        nodes: designState.value.nodes.filter((node) => node.id !== id),
    });

    if (selectedId.value === id) {
        selectedId.value = null;
    }
};

const addText = () => {
    const id = createNodeId();

    replaceDesignState({
        ...designState.value,
        nodes: [
            ...designState.value.nodes,
            normalizeNode({
                id,
                type: 'text',
                x: 140,
                y: 140,
                text: 'Your event text',
                fontSize: 42,
                fill: '#0f172a',
                width: 320,
                height: 60,
            }),
        ],
    });

    selectedId.value = id;
};

const createPlaceholderDataUrl = (count, slot) => {
    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1200">
            <defs>
                <linearGradient id="bg" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#f8fafc"/>
                    <stop offset="100%" stop-color="#dbeafe"/>
                </linearGradient>
            </defs>
            <rect width="800" height="1200" rx="42" fill="url(#bg)"/>
            <rect x="26" y="26" width="748" height="1148" rx="34" fill="none" stroke="#94a3b8" stroke-width="14" stroke-dasharray="28 18"/>
            <circle cx="400" cy="420" r="126" fill="#94a3b8" opacity="0.9"/>
            <path d="M190 980c38-175 145-270 210-270s172 95 210 270" fill="#94a3b8" opacity="0.9"/>
            <text x="400" y="120" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="74" font-weight="700" fill="#0f172a">Photo ${slot}</text>
            <text x="400" y="1090" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="48" fill="#334155">Layout ${count} image${count > 1 ? 's' : ''}</text>
        </svg>
    `;

    return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
};

const imageLayoutNodes = (count) => {
    const gap = 24;
    const outerPadding = 28;
    const slotHeight = Math.max(150, Math.floor((designState.value.height - (outerPadding * 2) - (gap * (count - 1))) / count));
    const slotWidth = Math.max(designState.value.width - (outerPadding * 2), 120);

    return Array.from({ length: count }, (_, index) => normalizeNode({
        id: createNodeId(),
        type: 'image',
        x: outerPadding,
        y: outerPadding + (index * (slotHeight + gap)),
        width: slotWidth,
        height: slotHeight,
        src: createPlaceholderDataUrl(count, index + 1),
        templateKind: 'photo-placeholder',
    }));
};

const applyImageLayout = (count) => {
    const nextNodes = [
        ...designState.value.nodes.filter((node) => node.templateKind !== 'photo-placeholder'),
        ...imageLayoutNodes(count),
    ];

    replaceDesignState({
        ...designState.value,
        nodes: nextNodes,
    });

    selectedId.value = nextNodes.at(-1)?.id ?? null;
};

const applyCanvasPreset = (presetValue) => {
    const preset = canvasPresets.find((entry) => entry.value === presetValue);

    if (!preset) {
        return;
    }

    replaceDesignState({
        ...designState.value,
        width: preset.width,
        height: preset.height,
    });
};

const triggerImageUpload = () => fileInput.value?.click();

const onImageSelected = async (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    error.value = '';

    const formData = new FormData();
    formData.append('image', file);

    try {
        const response = await axios.post(props.data.routes.uploadAsset, formData, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'multipart/form-data',
            },
        });

        const asset = response.data.record;
        const id = createNodeId();

        replaceDesignState({
            ...designState.value,
            nodes: [
                ...designState.value.nodes,
                normalizeNode({
                    id,
                    type: 'image',
                    x: 180,
                    y: 180,
                    width: 320,
                    height: 240,
                    src: asset.url,
                }),
            ],
        });

        selectedId.value = id;
        loadImage({ id, src: asset.url });
    } catch (uploadError) {
        error.value = uploadError.response?.data?.message ?? 'Image upload failed.';
    } finally {
        event.target.value = '';
    }
};

const removeSelectedNode = () => {
    if (!selectedId.value) {
        return;
    }

    removeNodeById(selectedId.value);
};

const deleteHandlePosition = (node) => ({
    x: node.x + (node.width * node.scaleX) - 12,
    y: node.y - 12,
});

const moveSelectedNode = (direction) => {
    const index = selectedNodeIndex.value;

    if (index < 0) {
        return;
    }

    const nodes = [...designState.value.nodes];
    const [node] = nodes.splice(index, 1);

    if (!node) {
        return;
    }

    let targetIndex = index;

    if (direction === 'forward') {
        targetIndex = Math.min(nodes.length, index + 1);
    } else if (direction === 'backward') {
        targetIndex = Math.max(0, index - 1);
    } else if (direction === 'front') {
        targetIndex = nodes.length;
    } else if (direction === 'back') {
        targetIndex = 0;
    }

    nodes.splice(targetIndex, 0, node);

    replaceDesignState({
        ...designState.value,
        nodes,
    });
};

const composeFontStyle = (isBold, isItalic) => {
    if (isBold && isItalic) {
        return 'bold italic';
    }

    if (isBold) {
        return 'bold';
    }

    if (isItalic) {
        return 'italic';
    }

    return 'normal';
};

const toggleSelectedTextStyle = (style) => {
    if (!selectedNode.value || selectedNode.value.type !== 'text') {
        return;
    }

    const nextBold = style === 'bold' ? !selectedNodeIsBold.value : selectedNodeIsBold.value;
    const nextItalic = style === 'italic' ? !selectedNodeIsItalic.value : selectedNodeIsItalic.value;

    updateNode(selectedNode.value.id, {
        fontStyle: composeFontStyle(nextBold, nextItalic),
    });
};

const saveDraft = async () => {
    saving.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await axios.put(
            props.data.routes.save,
            {
                title: title.value,
                design_data: cloneDesign(designState.value),
            },
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        lastSavedLabel.value = response.data.record?.last_saved_at_label ?? lastSavedLabel.value;
        message.value = response.data.message ?? 'Draft saved.';
    } catch (saveError) {
        error.value = saveError.response?.data?.message ?? 'Draft could not be saved.';
    } finally {
        saving.value = false;
    }
};

const undoChange = () => {
    const previous = undoStack.value.at(-1);

    if (!previous) {
        return;
    }

    undoStack.value = undoStack.value.slice(0, -1);
    isRestoringHistory.value = true;
    designState.value = normalizeDesign(previous);
    resetLoadedImages(designState.value.nodes);

    if (selectedId.value && !designState.value.nodes.some((node) => node.id === selectedId.value)) {
        selectedId.value = null;
    }

    nextTick(() => {
        isRestoringHistory.value = false;
    });
};

const resetDesign = () => {
    replaceDesignState({
        width: designState.value.width,
        height: designState.value.height,
        backgroundColor: defaultDesign.backgroundColor,
        nodes: [],
    });
    selectedId.value = null;
};

const zoomIn = () => {
    zoomScale.value = Math.min(2, Number((zoomScale.value + 0.1).toFixed(2)));
};

const zoomOut = () => {
    zoomScale.value = Math.max(0.3, Number((zoomScale.value - 0.1).toFixed(2)));
};

const resetZoom = () => {
    zoomScale.value = 0.65;
};

const stageStyle = computed(() => ({
    width: `${designState.value.width * zoomScale.value}px`,
    height: `${designState.value.height * zoomScale.value}px`,
}));

const stageInnerStyle = computed(() => ({
    width: `${designState.value.width}px`,
    height: `${designState.value.height}px`,
    transform: `scale(${zoomScale.value})`,
    transformOrigin: 'top left',
}));

onMounted(() => {
    loadConfiguredFonts();
});
</script>

<template>
    <section class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
        <aside class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
            <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Design Editor</p>
            <div class="mt-6 space-y-4">
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-[0.2em] text-stone-400">Draft Title</label>
                    <input v-model="title" type="text" class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/50">
                </div>

                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                    <button type="button" class="rounded-2xl bg-cyan-300 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200" @click="addText">
                        Add Text
                    </button>
                    <button type="button" class="rounded-2xl border border-white/10 px-4 py-3 text-sm font-semibold text-stone-100 transition hover:bg-white/5" @click="triggerImageUpload">
                        Add Image
                    </button>
                </div>

                <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onImageSelected">

                <div class="rounded-2xl border border-white/10 bg-slate-950/50 p-3.5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-white">Selected Layer</p>
                        <button type="button" class="text-xs font-medium text-rose-200 transition hover:text-rose-100 disabled:opacity-40" :disabled="!selectedNode" @click="removeSelectedNode">
                            Remove
                        </button>
                    </div>

                    <div v-if="selectedNode" class="mt-3 space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="rounded-xl border border-white/10 px-2.5 py-1.5 text-[11px] font-medium text-stone-100 transition hover:bg-white/5 disabled:opacity-40" :disabled="selectedNodeIndex <= 0" @click="moveSelectedNode('backward')">
                                Move Back
                            </button>
                            <button type="button" class="rounded-xl border border-white/10 px-2.5 py-1.5 text-[11px] font-medium text-stone-100 transition hover:bg-white/5 disabled:opacity-40" :disabled="selectedNodeIndex < 0 || selectedNodeIndex >= designState.nodes.length - 1" @click="moveSelectedNode('forward')">
                                Move Forward
                            </button>
                            <button type="button" class="rounded-xl border border-white/10 px-2.5 py-1.5 text-[11px] font-medium text-stone-100 transition hover:bg-white/5 disabled:opacity-40" :disabled="selectedNodeIndex <= 0" @click="moveSelectedNode('back')">
                                Send To Back
                            </button>
                            <button type="button" class="rounded-xl border border-white/10 px-2.5 py-1.5 text-[11px] font-medium text-stone-100 transition hover:bg-white/5 disabled:opacity-40" :disabled="selectedNodeIndex < 0 || selectedNodeIndex >= designState.nodes.length - 1" @click="moveSelectedNode('front')">
                                Bring To Front
                            </button>
                        </div>

                        <div v-if="selectedNode.type === 'text'">
                            <label class="mb-1 block text-[11px] uppercase tracking-[0.2em] text-stone-400">Text</label>
                            <textarea :value="selectedNode.text" rows="3" class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/50" @input="updateNode(selectedNode.id, { text: $event.target.value })" />
                        </div>

                        <div v-if="selectedNode.type === 'text'">
                            <label class="mb-1 block text-[11px] uppercase tracking-[0.2em] text-stone-400">Font</label>
                            <select :value="selectedNode.fontFamily" class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/50" @change="updateNode(selectedNode.id, { fontFamily: $event.target.value })">
                                <option v-for="font in fontOptions" :key="font.value" :value="font.value">{{ font.label }}</option>
                            </select>
                        </div>

                        <div v-if="selectedNode.type === 'text'" class="grid grid-cols-2 gap-2">
                            <button type="button" class="rounded-2xl border px-3 py-2 text-sm font-semibold transition" :class="selectedNodeIsBold ? 'border-cyan-300/40 bg-cyan-300/10 text-cyan-100' : 'border-white/10 text-stone-100 hover:bg-white/5'" @click="toggleSelectedTextStyle('bold')">
                                B
                            </button>
                            <button type="button" class="rounded-2xl border px-3 py-2 text-sm italic font-semibold transition" :class="selectedNodeIsItalic ? 'border-cyan-300/40 bg-cyan-300/10 text-cyan-100' : 'border-white/10 text-stone-100 hover:bg-white/5'" @click="toggleSelectedTextStyle('italic')">
                                I
                            </button>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div v-if="selectedNode.type === 'text'">
                                <label class="mb-1 block text-[11px] uppercase tracking-[0.2em] text-stone-400">Font Size</label>
                                <input :value="selectedNode.fontSize" type="number" min="12" max="200" class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/50" @input="updateNode(selectedNode.id, { fontSize: Number($event.target.value || 42) })">
                            </div>

                            <div v-if="selectedNode.type === 'text'">
                                <label class="mb-1 block text-[11px] uppercase tracking-[0.2em] text-stone-400">Text Color</label>
                                <input :value="selectedNode.fill" type="color" class="h-12 w-full rounded-2xl border border-white/10 bg-slate-950/70 px-2 py-2" @input="updateNode(selectedNode.id, { fill: $event.target.value })">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-[10px] uppercase tracking-[0.18em] text-stone-400">X</label>
                                <input :value="Math.round(selectedNode.x)" type="number" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-cyan-300/50" @input="updateNode(selectedNode.id, { x: Number($event.target.value || 0) })">
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] uppercase tracking-[0.18em] text-stone-400">Y</label>
                                <input :value="Math.round(selectedNode.y)" type="number" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-cyan-300/50" @input="updateNode(selectedNode.id, { y: Number($event.target.value || 0) })">
                            </div>
                        </div>
                    </div>

                    <p v-else class="mt-4 text-sm text-stone-400">Select a text or image layer to edit its settings.</p>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-[0.2em] text-stone-400">Canvas Background</label>
                    <input :value="designState.backgroundColor" type="color" class="h-12 w-full rounded-2xl border border-white/10 bg-slate-950/70 px-2 py-2" @input="replaceDesignState({ ...designState, backgroundColor: $event.target.value })">
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-950/50 p-4 text-sm">
                    <p class="text-stone-200">Saved state</p>
                    <p class="mt-1 text-stone-400">{{ lastSavedLabel || 'Not saved yet' }}</p>
                    <p v-if="message" class="mt-3 text-emerald-200">{{ message }}</p>
                    <p v-if="error" class="mt-3 text-rose-200">{{ error }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-2xl bg-cyan-300 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving" @click="saveDraft">
                        {{ saving ? 'Saving...' : 'Save Draft' }}
                    </button>
                    <a :href="data.routes.portal" class="rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-stone-100 transition hover:bg-white/5">
                        Back To Portal
                    </a>
                </div>
            </div>
        </aside>

        <section class="rounded-3xl border border-white/10 bg-[#0f172a] p-4 shadow-2xl shadow-black/20">
            <div class="mb-4 rounded-3xl border border-white/10 bg-white/[0.04] p-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">Canvas Tools</p>
                        <p class="mt-1 text-xs text-stone-400">Compact controls for size, layout, zoom, and quick recovery.</p>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-white/10 bg-slate-950/35 p-3">
                        <p class="text-[10px] uppercase tracking-[0.22em] text-stone-400">Canvas Size</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="preset in canvasPresets"
                                :key="preset.value"
                                type="button"
                                class="rounded-xl border px-3 py-1.5 text-xs font-semibold transition"
                                :class="activeCanvasPreset === preset.value ? 'border-cyan-300/40 bg-cyan-300/10 text-cyan-100' : 'border-white/10 text-stone-100 hover:bg-white/5'"
                                @click="applyCanvasPreset(preset.value)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-slate-950/35 p-3">
                        <p class="text-[10px] uppercase tracking-[0.22em] text-stone-400">Photo Layout</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="count in [1, 2, 3, 4]"
                                :key="count"
                                type="button"
                                class="rounded-xl border border-white/10 px-3 py-1.5 text-xs font-semibold text-stone-100 transition hover:bg-white/5"
                                @click="applyImageLayout(count)"
                            >
                                {{ count }}
                            </button>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-slate-950/35 p-3">
                        <p class="text-[10px] uppercase tracking-[0.22em] text-stone-400">Zoom</p>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="button" class="rounded-xl border border-white/10 px-3 py-1.5 text-xs font-semibold text-stone-100 transition hover:bg-white/5" @click="zoomOut">
                                -
                            </button>
                            <button type="button" class="min-w-[4.5rem] rounded-xl border border-white/10 px-3 py-1.5 text-xs font-semibold text-stone-100 transition hover:bg-white/5" @click="resetZoom">
                                {{ zoomPercent }}
                            </button>
                            <button type="button" class="rounded-xl border border-white/10 px-3 py-1.5 text-xs font-semibold text-stone-100 transition hover:bg-white/5" @click="zoomIn">
                                +
                            </button>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-slate-950/35 p-3">
                        <p class="text-[10px] uppercase tracking-[0.22em] text-stone-400">History</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" class="rounded-xl border px-3 py-1.5 text-xs font-semibold transition" :class="canUndo ? 'border-white/10 text-white hover:bg-white/5' : 'border-white/10 text-stone-500 opacity-60'" :disabled="!canUndo" @click="undoChange">
                                Undo
                            </button>
                            <button type="button" class="rounded-xl border border-rose-300/30 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-300/10" @click="resetDesign">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-auto rounded-3xl border border-dashed border-white/10 bg-slate-950/40 p-4">
                <div class="mx-auto" :style="stageStyle">
                    <div :style="stageInnerStyle">
                    <v-stage ref="stageRef" :config="{ width: designState.width, height: designState.height }">
                        <v-layer>
                            <v-rect :config="{ x: 0, y: 0, width: designState.width, height: designState.height, fill: designState.backgroundColor }" />

                            <template v-for="node in designState.nodes" :key="node.id">
                                <v-text
                                    v-if="node.type === 'text'"
                                    :ref="(instance) => setNodeRef(node.id, instance)"
                                    :config="{
                                        x: node.x,
                                        y: node.y,
                                        rotation: node.rotation,
                                        scaleX: node.scaleX,
                                        scaleY: node.scaleY,
                                        text: node.text,
                                        fontSize: node.fontSize,
                                        fontFamily: node.fontFamily,
                                        fontStyle: node.fontStyle,
                                        fill: node.fill,
                                        draggable: true,
                                        width: node.width,
                                    }"
                                    @click="selectedId = node.id"
                                    @tap="selectedId = node.id"
                                    @dragend="updateNode(node.id, { x: $event.target.x(), y: $event.target.y() })"
                                    @transformend="updateNode(node.id, { x: $event.target.x(), y: $event.target.y(), rotation: $event.target.rotation(), width: Math.max(120, $event.target.width() * $event.target.scaleX()), height: Math.max(40, node.height * $event.target.scaleY()), scaleX: 1, scaleY: 1 })"
                                />

                                <v-image
                                    v-else-if="node.type === 'image' && imageElements[node.id]"
                                    :ref="(instance) => setNodeRef(node.id, instance)"
                                    :config="{
                                        x: node.x,
                                        y: node.y,
                                        rotation: node.rotation,
                                        scaleX: node.scaleX,
                                        scaleY: node.scaleY,
                                        image: imageElements[node.id],
                                        width: node.width,
                                        height: node.height,
                                        draggable: true,
                                    }"
                                    @click="selectedId = node.id"
                                    @tap="selectedId = node.id"
                                    @dragend="updateNode(node.id, { x: $event.target.x(), y: $event.target.y() })"
                                    @transformend="updateNode(node.id, { x: $event.target.x(), y: $event.target.y(), rotation: $event.target.rotation(), width: Math.max(80, node.width * $event.target.scaleX()), height: Math.max(80, node.height * $event.target.scaleY()), scaleX: 1, scaleY: 1 })"
                                />

                                <template v-if="selectedId === node.id">
                                    <v-rect
                                        :config="{
                                            x: deleteHandlePosition(node).x,
                                            y: deleteHandlePosition(node).y,
                                            width: 24,
                                            height: 24,
                                            cornerRadius: 12,
                                            fill: '#ef4444',
                                            stroke: '#ffffff',
                                            strokeWidth: 1.5,
                                        }"
                                        @click="removeNodeById(node.id)"
                                        @tap="removeNodeById(node.id)"
                                    />
                                    <v-text
                                        :config="{
                                            x: deleteHandlePosition(node).x + 6.5,
                                            y: deleteHandlePosition(node).y + 3,
                                            text: '×',
                                            fontSize: 16,
                                            fontStyle: 'bold',
                                            fill: '#ffffff',
                                        }"
                                        @click="removeNodeById(node.id)"
                                        @tap="removeNodeById(node.id)"
                                    />
                                </template>
                            </template>

                            <v-transformer
                                ref="transformerRef"
                                :config="{
                                    rotateEnabled: true,
                                    keepRatio: false,
                                    enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
                                    borderStroke: '#22d3ee',
                                    anchorStroke: '#22d3ee',
                                    anchorFill: '#0f172a',
                                    anchorSize: 10,
                                }"
                            />
                        </v-layer>
                    </v-stage>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-3xl border border-white/10 bg-white/[0.04] p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.25em] text-cyan-200">Remarks</p>
                        <p class="mt-1 text-xs text-stone-400">Add notes for the team about this exact layout, spacing, photo order, or anything you want adjusted.</p>
                    </div>
                </div>

                <textarea
                    :value="designState.remarks"
                    rows="4"
                    maxlength="2000"
                    class="mt-3 w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/50"
                    placeholder="Example: Please use our family photo in the top frame and keep the event date larger near the bottom."
                    @input="replaceDesignState({ ...designState, remarks: $event.target.value })"
                />
                <div class="mt-2 flex justify-end">
                    <p class="text-[11px] text-stone-500">{{ String(designState.remarks ?? '').length }}/2000</p>
                </div>
            </div>
        </section>
    </section>
</template>
