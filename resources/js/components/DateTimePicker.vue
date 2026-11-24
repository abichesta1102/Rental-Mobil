<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight, Clock, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string | null;
        id?: string;
        placeholder?: string;
        min?: string | null;
        disabled?: boolean;
    }>(),
    {
        modelValue: '',
        id: undefined,
        placeholder: 'Pilih tanggal & waktu...',
        min: null,
        disabled: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const containerRef = ref<HTMLElement | null>(null);
const isOpen = ref(false);

const monthNames = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

const dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

function padZero(n: number | string): string {
    return String(n).padStart(2, '0');
}

const today = new Date();
const viewYear = ref(today.getFullYear());
const viewMonth = ref(today.getMonth()); // 0-11

const selectedDate = ref<string>(''); // YYYY-MM-DD
// Deteksi langsung jam saat ini
const selectedHour = ref<string>(padZero(today.getHours()));
const selectedMinute = ref<string>('00');

function getTodayStr(): string {
    return `${today.getFullYear()}-${padZero(today.getMonth() + 1)}-${padZero(today.getDate())}`;
}

function getDefaultDateStr(): string {
    const todayStr = getTodayStr();
    if (props.min) {
        const minDateStr = props.min.substring(0, 10);
        if (minDateStr > todayStr) {
            return minDateStr;
        }
    }
    return todayStr;
}

function parseModelValue(val?: string | null): void {
    if (!val) {
        selectedDate.value = '';
        return;
    }
    const dateObj = new Date(val);
    if (!Number.isNaN(dateObj.getTime())) {
        const y = dateObj.getFullYear();
        const m = padZero(dateObj.getMonth() + 1);
        const d = padZero(dateObj.getDate());
        selectedDate.value = `${y}-${m}-${d}`;
        selectedHour.value = padZero(dateObj.getHours());
        selectedMinute.value = padZero(dateObj.getMinutes());

        viewYear.value = dateObj.getFullYear();
        viewMonth.value = dateObj.getMonth();
    }
}

watch(
    () => props.modelValue,
    (newVal) => {
        parseModelValue(newVal);
    },
    { immediate: true },
);

onClickOutside(containerRef, () => {
    isOpen.value = false;
});

function emitValue(dateStr: string, hourStr: string, minStr: string): void {
    if (!dateStr) {
        emit('update:modelValue', '');
        return;
    }
    const result = `${dateStr}T${padZero(hourStr)}:${padZero(minStr)}`;
    emit('update:modelValue', result);
}

function ensureDateSelected(): void {
    if (!selectedDate.value) {
        selectedDate.value = getDefaultDateStr();
    }
}

function toggleDropdown(): void {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        if (selectedDate.value) {
            const parts = selectedDate.value.split('-');
            if (parts.length === 3) {
                viewYear.value = parseInt(parts[0], 10);
                viewMonth.value = parseInt(parts[1], 10) - 1;
            }
        } else {
            // Automatically select default date and current time
            const now = new Date();
            const defDate = getDefaultDateStr();
            selectedDate.value = defDate;

            // Jika ada min dan min memiliki format waktu T.. (misal tanggal pengembalian),
            // gunakan jam dari min secara default agar pas 24 jam
            if (props.min && props.min.includes('T')) {
                const timePart = props.min.split('T')[1];
                if (timePart) {
                    const [h, m] = timePart.split(':');
                    if (h) selectedHour.value = padZero(h);
                    if (m) selectedMinute.value = padZero(m);
                }
            } else {
                selectedHour.value = padZero(now.getHours());
                selectedMinute.value = '00';
            }

            const parts = defDate.split('-');
            if (parts.length === 3) {
                viewYear.value = parseInt(parts[0], 10);
                viewMonth.value = parseInt(parts[1], 10) - 1;
            }
            emitValue(selectedDate.value, selectedHour.value, selectedMinute.value);
        }
    }
}

function prevMonth(): void {
    if (viewMonth.value === 0) {
        viewMonth.value = 11;
        viewYear.value -= 1;
    } else {
        viewMonth.value -= 1;
    }
}

function nextMonth(): void {
    if (viewMonth.value === 11) {
        viewMonth.value = 0;
        viewYear.value += 1;
    } else {
        viewMonth.value += 1;
    }
}

interface CalendarCell {
    day: number;
    month: number;
    year: number;
    dateString: string;
    isCurrentMonth: boolean;
    isToday: boolean;
    isSelected: boolean;
    isDisabled: boolean;
}

const calendarGrid = computed<CalendarCell[]>(() => {
    const year = viewYear.value;
    const month = viewMonth.value;

    const firstDayIndex = new Date(year, month, 1).getDay(); // 0 is Sunday
    const daysInCurrentMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    const todayStr = getTodayStr();
    const minDateStr = props.min ? props.min.substring(0, 10) : '';

    const cells: CalendarCell[] = [];

    // Leading days from previous month
    for (let i = firstDayIndex - 1; i >= 0; i--) {
        const d = daysInPrevMonth - i;
        const prevM = month === 0 ? 11 : month - 1;
        const prevY = month === 0 ? year - 1 : year;
        const dateStr = `${prevY}-${padZero(prevM + 1)}-${padZero(d)}`;

        cells.push({
            day: d,
            month: prevM,
            year: prevY,
            dateString: dateStr,
            isCurrentMonth: false,
            isToday: dateStr === todayStr,
            isSelected: dateStr === selectedDate.value,
            isDisabled: !!minDateStr && dateStr < minDateStr,
        });
    }

    // Days in current month
    for (let d = 1; d <= daysInCurrentMonth; d++) {
        const dateStr = `${year}-${padZero(month + 1)}-${padZero(d)}`;

        cells.push({
            day: d,
            month,
            year,
            dateString: dateStr,
            isCurrentMonth: true,
            isToday: dateStr === todayStr,
            isSelected: dateStr === selectedDate.value,
            isDisabled: !!minDateStr && dateStr < minDateStr,
        });
    }

    // Trailing days from next month
    const totalFilled = cells.length;
    const remainder = totalFilled % 7;
    const daysToAdd = remainder === 0 ? 0 : 7 - remainder;

    for (let d = 1; d <= daysToAdd; d++) {
        const nextM = month === 11 ? 0 : month + 1;
        const nextY = month === 11 ? year + 1 : year;
        const dateStr = `${nextY}-${padZero(nextM + 1)}-${padZero(d)}`;

        cells.push({
            day: d,
            month: nextM,
            year: nextY,
            dateString: dateStr,
            isCurrentMonth: false,
            isToday: dateStr === todayStr,
            isSelected: dateStr === selectedDate.value,
            isDisabled: !!minDateStr && dateStr < minDateStr,
        });
    }

    return cells;
});

function selectDay(cell: CalendarCell): void {
    if (cell.isDisabled) return;
    selectedDate.value = cell.dateString;
    emitValue(selectedDate.value, selectedHour.value, selectedMinute.value);
}

function onHourChange(e: Event): void {
    const val = (e.target as HTMLSelectElement).value;
    selectedHour.value = val;
    ensureDateSelected();
    emitValue(selectedDate.value, selectedHour.value, selectedMinute.value);
}

function onMinuteChange(e: Event): void {
    const val = (e.target as HTMLSelectElement).value;
    selectedMinute.value = val;
    ensureDateSelected();
    emitValue(selectedDate.value, selectedHour.value, selectedMinute.value);
}

function selectToday(): void {
    const now = new Date();
    const y = now.getFullYear();
    const m = padZero(now.getMonth() + 1);
    const d = padZero(now.getDate());
    selectedDate.value = `${y}-${m}-${d}`;
    selectedHour.value = padZero(now.getHours());
    selectedMinute.value = '00';
    viewYear.value = y;
    viewMonth.value = now.getMonth();
    emitValue(selectedDate.value, selectedHour.value, selectedMinute.value);
}

function clearValue(e?: MouseEvent): void {
    if (e) e.stopPropagation();
    selectedDate.value = '';
    emit('update:modelValue', '');
}

function confirmSelection(): void {
    ensureDateSelected();
    emitValue(selectedDate.value, selectedHour.value, selectedMinute.value);
    isOpen.value = false;
}

const displayFormatted = computed<string>(() => {
    if (!props.modelValue) return '';
    try {
        const d = new Date(props.modelValue);
        if (Number.isNaN(d.getTime())) return props.modelValue;
        return new Intl.DateTimeFormat('id-ID', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(d);
    } catch {
        return props.modelValue;
    }
});

const minuteOptions = ['00', '15', '30', '45'];
const hourOptions = Array.from({ length: 24 }, (_, i) => padZero(i));
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <!-- Trigger button mimicking an input -->
        <button
            :id="id"
            type="button"
            :disabled="disabled"
            class="border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full items-center justify-between rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
            :class="{ 'ring-[3px] ring-ring/50 border-ring': isOpen }"
            @click="toggleDropdown"
        >
            <div class="flex items-center gap-2 truncate">
                <CalendarIcon class="h-4 w-4 shrink-0 text-muted-foreground" />
                <span v-if="displayFormatted" class="font-medium text-foreground truncate">
                    {{ displayFormatted }}
                </span>
                <span v-else class="text-muted-foreground truncate">
                    {{ placeholder }}
                </span>
            </div>

            <div class="flex items-center gap-1">
                <span
                    v-if="modelValue && !disabled"
                    role="button"
                    title="Hapus"
                    class="rounded-full p-0.5 hover:bg-muted text-muted-foreground hover:text-foreground"
                    @click="clearValue"
                >
                    <X class="h-3.5 w-3.5" />
                </span>
            </div>
        </button>

        <!-- Calendar Dropdown Popover -->
        <div
            v-if="isOpen"
            class="absolute left-0 top-full z-50 mt-1.5 w-72 sm:w-80 rounded-xl border bg-popover text-popover-foreground shadow-xl p-3 animate-in fade-in-0 zoom-in-95"
        >
            <!-- Month navigation header -->
            <div class="flex items-center justify-between pb-2 mb-2 border-b">
                <button
                    type="button"
                    class="h-7 w-7 rounded-md flex items-center justify-center hover:bg-muted text-muted-foreground hover:text-foreground transition-colors"
                    @click="prevMonth"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <div class="font-semibold text-sm">
                    {{ monthNames[viewMonth] }} {{ viewYear }}
                </div>
                <button
                    type="button"
                    class="h-7 w-7 rounded-md flex items-center justify-center hover:bg-muted text-muted-foreground hover:text-foreground transition-colors"
                    @click="nextMonth"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>

            <!-- Days of week -->
            <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-muted-foreground mb-1">
                <div v-for="day in dayNames" :key="day" class="py-1">
                    {{ day }}
                </div>
            </div>

            <!-- Calendar Days Grid -->
            <div class="grid grid-cols-7 gap-1 text-center text-xs">
                <button
                    v-for="(cell, index) in calendarGrid"
                    :key="index"
                    type="button"
                    :disabled="cell.isDisabled"
                    class="h-8 w-8 mx-auto rounded-md flex flex-col items-center justify-center transition-colors relative"
                    :class="[
                        cell.isSelected
                            ? 'bg-primary text-primary-foreground font-semibold shadow-xs'
                            : cell.isCurrentMonth
                              ? 'text-foreground hover:bg-muted'
                              : 'text-muted-foreground/40 hover:bg-muted/50',
                        cell.isDisabled ? 'opacity-30 cursor-not-allowed hover:bg-transparent' : 'cursor-pointer',
                    ]"
                    @click="selectDay(cell)"
                >
                    <span>{{ cell.day }}</span>
                    <!-- Small dot for today if not currently selected -->
                    <span
                        v-if="cell.isToday && !cell.isSelected"
                        class="absolute bottom-1 h-1 w-1 rounded-full bg-primary"
                    />
                </button>
            </div>

            <!-- Time Picker section -->
            <div class="mt-3 pt-3 border-t flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-medium">
                    <Clock class="h-3.5 w-3.5" />
                    <span>Jam:</span>
                </div>

                <div class="flex items-center gap-1.5">
                    <select
                        :value="selectedHour"
                        class="h-7 rounded-md border border-input bg-background px-2 text-xs shadow-xs outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        @change="onHourChange"
                    >
                        <option v-for="h in hourOptions" :key="h" :value="h">
                            {{ h }}
                        </option>
                    </select>
                    <span class="text-xs font-semibold">:</span>
                    <select
                        :value="selectedMinute"
                        class="h-7 rounded-md border border-input bg-background px-2 text-xs shadow-xs outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        @change="onMinuteChange"
                    >
                        <option v-for="m in minuteOptions" :key="m" :value="m">
                            {{ m }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="mt-3 pt-2 border-t flex items-center justify-between">
                <button
                    type="button"
                    class="text-xs text-primary hover:underline font-medium"
                    @click="selectToday"
                >
                    Hari Ini
                </button>
                <div class="flex items-center gap-2">
                    <button
                        v-if="modelValue"
                        type="button"
                        class="text-xs text-muted-foreground hover:text-foreground hover:underline"
                        @click="clearValue()"
                    >
                        Reset
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-primary text-primary-foreground hover:bg-primary/90 px-3 py-1 text-xs font-medium transition-colors shadow-xs"
                        @click="confirmSelection"
                    >
                        Pilih
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
