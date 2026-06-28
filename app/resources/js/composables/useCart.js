import { computed, ref, watch } from 'vue';

const cart = ref(JSON.parse(localStorage.getItem('gouden_draak_cart') || '[]'));

watch(cart, (val) => {
    localStorage.setItem('gouden_draak_cart', JSON.stringify(val));
}, { deep: true });

export const useCart = () => {
    const items = computed(() => cart.value);
    
    const count = computed(() => cart.value.reduce((sum, item) => sum + item.quantity, 0));
    
    const total = computed(() => cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0));

    const addToCart = (menuItem) => {
        const existing = cart.value.find(i => i.id === menuItem.id);
        if (existing) {
            existing.quantity++;
        } else {
            cart.value.push({
                id: menuItem.id,
                name: menuItem.name,
                number: menuItem.display_number,
                price: menuItem.price,
                quantity: 1
            });
        }
    };

    const removeFromCart = (itemId) => {
        cart.value = cart.value.filter(i => i.id !== itemId);
    };

    const updateQuantity = (itemId, delta) => {
        const item = cart.value.find(i => i.id === itemId);
        if (item) {
            item.quantity = Math.max(1, item.quantity + delta);
        }
    };

    const clearCart = () => {
        cart.value = [];
    };

    return {
        items,
        count,
        total,
        addToCart,
        removeFromCart,
        updateQuantity,
        clearCart
    };
};
