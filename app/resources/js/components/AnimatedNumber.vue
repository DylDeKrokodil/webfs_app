<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    value: {
        type: Number,
        required: true
    },
    duration: {
        type: Number,
        default: 1000
    },
    decimals: {
        type: Number,
        default: 0
    },
    prefix: {
        type: String,
        default: ''
    },
    suffix: {
        type: String,
        default: ''
    }
});

const displayValue = ref(0);
let startTime = null;
let startValue = 0;

const formatNumber = (val) => {
    return props.prefix + Number(val).toFixed(props.decimals) + props.suffix;
};

const animate = (timestamp) => {
    if (!startTime) startTime = timestamp;
    const progress = Math.min((timestamp - startTime) / props.duration, 1);
    
    // Ease out quad function
    const ease = progress * (2 - progress);
    
    const currentVal = startValue + (props.value - startValue) * ease;
    displayValue.value = currentVal;
    
    if (progress < 1) {
        requestAnimationFrame(animate);
    }
};

const startAnimation = () => {
    startTime = null;
    startValue = displayValue.value;
    requestAnimationFrame(animate);
};

onMounted(startAnimation);

watch(() => props.value, (newVal) => {
    if (newVal !== displayValue.value) {
        startAnimation();
    }
});
</script>

<template>
    <span>{{ formatNumber(displayValue) }}</span>
</template>
