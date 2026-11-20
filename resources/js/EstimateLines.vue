<script setup lang="ts">
import { money, type Estimate } from './estimate';

defineProps<{ estimate: Estimate }>();
</script>

<template>
    <h3>Included essentials</h3>
    <ul class="receipt-lines">
        <li v-for="item in estimate.included" :key="item">{{ item }}</li>
    </ul>
    <template v-if="estimate.features.length">
        <h3>Requested scope</h3>
        <ul class="receipt-lines"><li v-for="item in estimate.features" :key="item">{{ item }}</li></ul>
    </template>
    <template v-if="estimate.one_time.length">
        <h3>One-time work</h3>
        <dl class="receipt-lines">
            <div v-for="item in estimate.one_time" :key="item.id">
                <dt>{{ item.name }} <small v-if="item.quantity > 1">× {{ item.quantity }}</small></dt>
                <dd>{{ money(item.amount) }}</dd>
            </div>
            <div v-if="estimate.rush"><dt>Priority delivery</dt><dd>{{ money(estimate.rush) }}</dd></div>
        </dl>
    </template>
    <template v-if="estimate.monthly.length">
        <h3>Optional monthly charges</h3>
        <dl class="receipt-lines">
            <div v-for="item in estimate.monthly" :key="item.name"><dt>{{ item.name }}</dt><dd>{{ money(item.amount) }}</dd></div>
        </dl>
    </template>
    <template v-if="estimate.provider.length">
        <h3>Separate provider costs</h3>
        <ul class="receipt-lines"><li v-for="item in estimate.provider" :key="item">{{ item }}</li></ul>
    </template>
    <template v-if="estimate.review.length">
        <h3>Needs review</h3>
        <ul class="receipt-lines">
            <li v-for="item in estimate.review" :key="item.id"><strong>{{ item.name }}</strong><br>{{ item.scope }}</li>
        </ul>
    </template>
    <template v-if="estimate.later.length">
        <h3>Saved for later</h3>
        <ul class="receipt-lines">
            <li v-for="item in estimate.later" :key="item.id">{{ item.name }} <small v-if="item.quantity > 1">× {{ item.quantity }}</small></li>
        </ul>
    </template>
</template>
