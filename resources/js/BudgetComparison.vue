<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { money, post, type Catalog, type Estimate } from './estimate';

type Option = {
    defer: Record<string, string>;
    total: number;
    savings: number;
    fits: boolean;
    remaining: string[];
    review: string[];
};
type Result = { total: number; review: string[]; options: Option[] };

const props = defineProps<{ estimate: Estimate; payload: string; addons: Catalog['addons']; loading: boolean }>();
const emit = defineEmits<{ apply: [ids: string[]] }>();
const budget = ref<number | ''>('');
const essential = ref<string[]>([]);
const result = ref<Result | null>(null);
const pending = ref(false);
const error = ref('');
const applied = ref('');
const choices = computed(() => [
    ...props.estimate.one_time.filter(item => item.id in props.addons).map(item => [item.id, item.name]),
    ...(props.estimate.rush ? [['priority', 'Priority delivery']] : []),
]);

watch([() => props.payload, budget, essential], () => { result.value = null; });

async function compare() {
    if (pending.value || props.loading || budget.value === '') return;
    const payload = props.payload;
    const target = Math.round(budget.value * 100);
    const protectedItems = [...essential.value];
    pending.value = true;
    result.value = null;
    error.value = '';
    applied.value = '';
    try {
        const response = await post('/estimate/budget', { ...JSON.parse(payload), budget_centavos: target, essential: protectedItems });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'The comparison could not be loaded.');
        if (payload === props.payload && Math.round(Number(budget.value) * 100) === target
            && JSON.stringify(protectedItems) === JSON.stringify(essential.value)) result.value = data as Result;
    } catch (failure) {
        error.value = failure instanceof Error ? failure.message : 'The comparison could not be loaded.';
    } finally {
        pending.value = false;
    }
}

function apply(option: Option) {
    applied.value = 'Saved for later · ' + Object.values(option.defer).join(', ');
    emit('apply', Object.keys(option.defer));
}
</script>

<template>
    <details class="comparison">
        <summary>What can I launch with my budget?</summary>
        <form @submit.prevent="compare">
            <label class="field">One-time budget (₱)
                <input v-model.number="budget" type="number" name="launch-budget" min="1" max="10000000" step=".01" required>
            </label>
            <fieldset v-if="choices.length">
                <legend>Which upgrades must be included?</legend>
                <label v-for="[id, name] in choices" :key="id" class="choice">
                    <input v-model="essential" type="checkbox" name="essential" :value="id"><span>{{ name }}</span>
                </label>
            </fieldset>
            <button :disabled="pending || loading">{{ pending ? 'Comparing…' : 'Compare with my budget' }}</button>
        </form>
        <p v-if="error" role="alert">{{ error }}</p>
        <p v-if="applied" role="status">{{ applied }}</p>
        <div v-if="result" aria-live="polite" class="budget-results">
            <p v-if="result.total <= Number(budget) * 100">The priced subtotal is within this budget.</p>
            <p v-else-if="!result.options.length">The priced subtotal stays above this budget with your essential scope.</p>
            <p v-if="result.review.length">Still needs review · {{ result.review.join(', ') }}. The comparison also excludes monthly charges and provider fees.</p>
            <p v-else>Monthly charges and provider fees are separate.</p>

            <article v-for="(option, index) in result.options" :key="Object.keys(option.defer).join('-')" class="budget-option">
                <h4>Save for later · {{ Object.values(option.defer).join(', ') }}</h4>
                <p>{{ money(option.total) }} for priced work · Save {{ money(option.savings) }}<span v-if="!option.fits"> · Still above budget</span></p>
                <p v-if="option.remaining.length">Keep {{ option.remaining.join(', ') }} and the included essentials.</p>
                <div class="actions">
                    <button type="button" @click="apply(option)">Apply this option</button>
                    <button type="button" class="secondary" @click="result.options.splice(index, 1)">Dismiss</button>
                </div>
            </article>
        </div>
    </details>
</template>
