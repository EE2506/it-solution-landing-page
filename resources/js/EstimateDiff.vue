<script setup lang="ts">
import { computed } from 'vue';
import { money, type Estimate } from './estimate';

const props = defineProps<{ before: Estimate; after: Estimate; heading: string; retired?: string[] }>();
const difference = computed(() => props.after.total - props.before.total);

function lines(estimate: Estimate) {
    return new Map([
        ...estimate.one_time.map(item => [item.id, { name: item.name, value: money(item.rate ?? 0) + ' × ' + item.quantity, scope: item.scope }] as const),
        ...estimate.review.map(item => [item.id, { name: item.name, value: 'Needs review', scope: item.scope }] as const),
        ...estimate.later.map(item => [item.id, { name: item.name, value: 'Saved for later', scope: item.scope }] as const),
    ]);
}

const changes = computed(() => {
    const before = lines(props.before);
    const after = lines(props.after);
    const result: string[] = [];
    for (const [id, prior] of before) {
        const updated = after.get(id);
        if (!updated) {
            result.push(prior.name + (props.retired?.includes(id) ? ' has been retired' : ' is no longer in the scope'));
        } else if (prior.value !== updated.value || prior.name !== updated.name) {
            result.push(prior.name + ' (' + prior.value + ') → ' + updated.name + ' (' + updated.value + ')');
        } else if (prior.scope !== updated.scope) {
            result.push('Updated scope for ' + updated.name + ' · ' + updated.scope);
        }
    }
    for (const [id, item] of after) {
        if (!before.has(id)) result.push('Added ' + item.name + ' · ' + item.value);
    }

    for (const [beforeGroup, afterGroup] of [
        [props.before.included, props.after.included],
        [props.before.features ?? [], props.after.features ?? []],
        [props.before.monthly.map(item => item.name + ' · ' + money(item.amount) + '/month'), props.after.monthly.map(item => item.name + ' · ' + money(item.amount) + '/month')],
        [props.before.provider, props.after.provider],
    ]) {
        for (const item of beforeGroup) if (!afterGroup.includes(item)) result.push('Removed ' + item);
        for (const item of afterGroup) if (!beforeGroup.includes(item)) result.push('Added ' + item);
    }
    if (props.before.rush !== props.after.rush) result.push('Priority fee · ' + money(props.before.rush) + ' → ' + money(props.after.rush));
    return result;
});
</script>

<template>
    <section class="comparison">
        <h3>{{ heading }}</h3>
        <p>
            Priced one-time work · {{ money(before.total) }} → {{ money(after.total) }}
            <span v-if="difference">({{ money(Math.abs(difference)) }} {{ difference > 0 ? 'more' : 'less' }})</span>
        </p>
        <ul v-if="changes.length"><li v-for="(change, index) in changes" :key="index">{{ change }}</li></ul>
        <p v-else>The priced total and scope are unchanged.</p>
    </section>
</template>
