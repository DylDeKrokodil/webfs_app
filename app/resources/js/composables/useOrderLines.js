import { computed, ref } from 'vue';
import { toastService } from '../services/toastService';

const cleanNote = (note) => String(note ?? '').replace(/\s+/g, ' ').trim();
const normalizeNote = (note) => cleanNote(note).toLowerCase();

const createOrderLine = (item, quantity = 1, notes = []) => ({
    id: item.id ?? item.menu_item_id,
    display_number: item.display_number,
    name: item.name,
    price: Number(item.price ?? item.current_price),
    quantity,
    notes: [...notes],
});

export const useOrderLines = ({ maxNotes = 5 } = {}) => {
    const orderLines = ref([]);
    const customNoteInputs = ref({});

    const lineCount = computed(() => orderLines.value.reduce((sum, line) => sum + line.quantity, 0));
    const orderTotal = computed(() => orderLines.value.reduce((sum, line) => sum + line.quantity * Number(line.price), 0));
    const itemQuantityById = computed(() => new Map(orderLines.value.map((line) => [line.id, line.quantity])));

    const addItem = (item) => {
        const existingLine = orderLines.value.find((line) => line.id === item.id);
        if (existingLine) {
            existingLine.quantity += 1;
            return;
        }

        orderLines.value.push(createOrderLine(item));
    };

    const setOrderLinesFromHistory = (lines) => {
        orderLines.value = lines
            .filter((line) => line.is_active)
            .map((line) => createOrderLine(line, line.quantity, line.notes ?? []));
    };

    const clearOrderLines = () => {
        orderLines.value = [];
        customNoteInputs.value = {};
    };

    const increaseQuantity = (line) => {
        line.quantity += 1;
    };

    const decreaseQuantity = (line) => {
        if (line.quantity <= 1) {
            orderLines.value = orderLines.value.filter((currentLine) => currentLine.id !== line.id);
            return;
        }

        line.quantity -= 1;
    };

    const addNoteToLine = (line, note) => {
        const cleanedNote = cleanNote(note);
        if (!cleanedNote) return;
        if (line.notes.some((existingNote) => normalizeNote(existingNote) === normalizeNote(cleanedNote))) return;
        if (line.notes.length >= maxNotes) {
            toastService.error(`Maximaal ${maxNotes} opmerkingen per gerecht.`);
            return;
        }

        line.notes.push(cleanedNote);
        customNoteInputs.value[line.id] = '';
    };

    const removeNoteFromLine = (line, note) => {
        line.notes = line.notes.filter((existingNote) => existingNote !== note);
    };

    return {
        orderLines,
        customNoteInputs,
        lineCount,
        orderTotal,
        itemQuantityById,
        addItem,
        setOrderLinesFromHistory,
        clearOrderLines,
        increaseQuantity,
        decreaseQuantity,
        addNoteToLine,
        removeNoteFromLine,
    };
};
