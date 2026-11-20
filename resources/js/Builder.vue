<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import EstimateLines from './EstimateLines.vue';
import { money, post, type Catalog, type Confirmation, type Preview, type Selection } from './estimate';

const props = defineProps<{ catalog: Catalog; requestKey: string }>();
const step = ref(0);
const ready = ref(false);
const heading = ref<HTMLElement | null>(null);
const requestKey = ref(props.requestKey);
const preview = ref<Preview | null>(null);
const outdated = ref<Preview | null>(null);
const confirmation = ref<Confirmation | null>(null);
const loading = ref(false);
const sending = ref(false);
const error = ref('');
const input = reactive({
    mode: '' as '' | 'guided' | 'direct',
    goal: '', service: '', appointment: '', installation: '', budget: '', timing: '',
    features: [] as string[], staff_count: '' as number | '',
    sms_source: '', booking_tool: '', email_purpose: '', monthly: [] as string[],
    addons: Object.fromEntries(Object.keys(props.catalog.addons).map(id => [id, { state: 'removed' as Selection, quantity: 1 }])),
});
const contact = reactive({ name: '', email: '', business: '', notes: '' });
const priority = ref<Selection>('removed');
const appointment = computed(() => input.mode === 'guided' && input.goal === 'appointments');
const goal = computed(() => props.catalog.goals[input.goal]);
const service = computed(() => input.mode === 'direct' ? input.service
    : appointment.value ? props.catalog.appointment_answers[input.appointment]?.service : goal.value?.service);
const installation = computed(() => input.installation || props.catalog.appointment_answers[input.appointment]?.installation);
const extras = computed(() => Object.entries(props.catalog.addons).filter(([id, item]) =>
    (!item.services || item.services.includes(service.value ?? ''))
    && !(id === 'booking' && service.value === 'installation' && installation.value === 'scheduler')));
const months = computed(() => props.catalog.months[service.value ?? ''] ?? {});
const estimate = computed(() => preview.value?.estimate);
const cashLabel = computed(() => !estimate.value?.one_time.length ? 'Needs review' : money(estimate.value.total));
const estimateJson = computed(() => JSON.stringify({ ...input, priority: priority.value === 'selected', priority_later: priority.value === 'later' }));

watch([estimateJson, ready], ([payload, active], _, onCleanup) => {
    if (!active) return;
    const controller = new AbortController();
    loading.value = true;
    error.value = '';
    const timer = window.setTimeout(async () => {
        try {
            const response = await post('/estimate', JSON.parse(payload), controller.signal);
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Check your selections and try again.');
            preview.value = data as Preview;
        } catch (failure) {
            if (!controller.signal.aborted) error.value = failure instanceof Error ? failure.message : 'The estimate could not be updated.';
        } finally {
            if (!controller.signal.aborted) loading.value = false;
        }
    }, 180);
    onCleanup(() => {
        clearTimeout(timer);
        controller.abort();
    });
}, { flush: 'sync' });

function changeStart() {
    ready.value = false;
    loading.value = false;
    preview.value = null;
    outdated.value = null;
    input.appointment = '';
    input.installation = '';
    input.monthly = [];
    input.features = [];
    input.staff_count = '';
}

function selectMode(mode: 'guided' | 'direct') {
    if (input.mode === mode) return;
    input.mode = mode;
    input.goal = '';
    input.service = '';
    changeStart();
}

async function go(next: number) {
    if (next === 1) ready.value = true;
    step.value = next;
    await nextTick();
    heading.value?.focus();
    heading.value?.scrollIntoView({ block: 'nearest' });
}

async function submit() {
    if (sending.value || loading.value || !preview.value) return;
    sending.value = true;
    error.value = '';
    try {
        const response = await post('/quotes', { ...JSON.parse(estimateJson.value), contact, request_key: requestKey.value, preview: preview.value.preview });
        const data = await response.json();
        if (response.status === 409 && data.estimate) {
            outdated.value = preview.value;
            preview.value = data as Preview;
            error.value = data.message;
            await go(2);
            return;
        }
        if (!response.ok) throw new Error(data.message || 'The request could not be saved. Try again.');
        confirmation.value = data as Confirmation;
        await go(4);
    } catch (failure) {
        error.value = failure instanceof Error ? failure.message : 'The request could not be saved. Try again.';
    } finally {
        sending.value = false;
    }
}

function restart() {
    window.location.assign('/');
}
</script>

<template>
    <a class="skip-link" href="#builder">Skip to project builder</a>
    <div class="shell">
        <header class="masthead"><a href="/">Independent developer</a><span>For Filipino businesses</span></header>
        <section class="intro" aria-labelledby="page-title">
            <div>
                <h1 id="page-title">Build your project<br class="desktop-break"> &amp; estimate</h1>
                <p>Tell me what your business needs. I’ll suggest a starting point and show what goes into your estimate.</p>
            </div>
            <p class="intro-note">Choose what fits now and save ideas for later. You can request a quote without an account.</p>
        </section>

        <main id="builder" class="builder-layout" :class="{ 'with-estimate': estimate && !confirmation }">
            <article class="workbench">
                <ol v-if="!confirmation" class="steps" aria-label="Estimate progress">
                    <li v-for="(label, index) in ['Start', 'Scope', 'Review', 'Contact']" :key="label" :aria-current="step === index ? 'step' : undefined">
                        <span aria-hidden="true">{{ index + 1 }}</span> {{ label }}
                    </li>
                </ol>
                <p v-if="error" role="alert" class="error">{{ error }}</p>
                <form v-if="step === 0" aria-labelledby="start-title" @submit.prevent="go(1)">
                    <h2 id="start-title" ref="heading" tabindex="-1">How would you like to start?</h2>
                    <fieldset class="choice-grid">
                        <legend class="sr-only">Starting route</legend>
                        <label class="choice"><input type="radio" name="mode" value="guided" :checked="input.mode === 'guided'" required @change="selectMode('guided')"><span><strong>Help me choose</strong><small>Start with a goal for your business</small></span></label>
                        <label class="choice"><input type="radio" name="mode" value="direct" :checked="input.mode === 'direct'" required @change="selectMode('direct')"><span><strong>I know what I need</strong><small>Pick a service or an installation</small></span></label>
                    </fieldset>
                    <label v-if="input.mode === 'guided'" class="field">What do you want to accomplish?
                        <select v-model="input.goal" name="goal" required @change="changeStart"><option disabled value="">Choose a goal</option><option v-for="(item, id) in catalog.goals" :key="id" :value="id">{{ item.name }}</option></select>
                    </label>
                    <label v-if="appointment" class="field">How will appointments be confirmed?
                        <select v-model="input.appointment" name="appointment" required><option disabled value="">Choose an answer</option><option v-for="(item, id) in catalog.appointment_answers" :key="id" :value="id">{{ item.name }}</option></select>
                    </label>
                    <label v-if="input.mode === 'direct'" class="field">Which service do you need?
                        <select v-model="input.service" name="service" required @change="changeStart"><option disabled value="">Choose a service</option><option v-for="(item, id) in catalog.services" :key="id" :value="id">{{ item.name }}</option></select>
                    </label>
                    <label v-if="input.mode === 'direct' && input.service === 'installation'" class="field">What needs to be installed?
                        <select v-model="input.installation" name="installation" required><option disabled value="">Choose an installation</option><option v-for="(item, id) in catalog.installations" :key="id" :value="id">{{ item.name }}</option></select>
                    </label>
                    <div class="actions"><button>Continue to scope</button></div>
                </form>

                <form v-else-if="step === 1" aria-labelledby="scope-title" @submit.prevent="go(2)">
                    <h2 id="scope-title" ref="heading" tabindex="-1">Shape your project</h2>
                    <section v-if="estimate" class="recommendation">
                        <h3>{{ estimate.name }}</h3>
                        <p>{{ estimate.why }}</p>
                        <p v-if="estimate.question"><strong>To discuss</strong> {{ estimate.question }}</p>
                        <h4>Included essentials</h4>
                        <ul><li v-for="item in estimate.included" :key="item">{{ item }}</li></ul>
                    </section>
                    <p v-else-if="loading" role="status">Preparing your starting point…</p>
                    <fieldset v-if="input.mode === 'guided' && goal?.features?.length" class="addon-list">
                        <legend>What should the project help you do?</legend>
                        <label v-for="id in goal.features" :key="id" class="choice">
                            <input v-model="input.features" type="checkbox" name="features" :value="id"><span>{{ catalog.feature_choices[id] }}</span>
                        </label>
                    </fieldset>
                    <label v-if="service === 'system'" class="field">How many staff will use it? (optional)
                        <input v-model.number="input.staff_count" type="number" name="staff_count" min="1" max="9999">
                    </label>
                    <fieldset class="addon-list">
                        <legend>Optional upgrades</legend>
                        <div v-for="[id, item] in extras" :key="id" class="addon">
                            <label :for="'addon-' + id"><strong>{{ item.name }}</strong><small>{{ item.description }}</small></label>
                            <select :id="'addon-' + id" v-model="input.addons[id].state" :name="'addon-' + id">
                                <option value="removed">Remove</option><option value="selected">Selected</option><option value="later">Save for later</option>
                            </select>
                            <label v-if="item.unit && input.addons[id].state !== 'removed'" class="field wide">{{ item.unit }}
                                <input v-model.number="input.addons[id].quantity" type="number" :name="'quantity-' + id" min="1" :max="item.max" required>
                            </label>
                            <label v-if="id === 'sms' && input.addons.sms.state === 'selected'" class="field wide">What tool records the appointment time or event?
                                <select v-model="input.sms_source" name="sms_source">
                                    <option value="">Choose an answer</option><option value="booking">My booking tool records the confirmed time</option><option value="calendar">My calendar stores the confirmed time</option><option value="other_event">Another supported tool records the event</option><option value="already_sends">My booking tool already sends the reminders</option><option value="unknown">I’m not sure or need a compatibility check</option>
                                </select>
                            </label>
                            <label v-if="id === 'booking' && input.addons.booking.state === 'selected'" class="field wide">Do you know which booking tool you’ll use?
                                <select v-model="input.booking_tool" name="booking_tool">
                                    <option value="">Choose an answer</option><option value="supported">I have a supported scheduler</option><option value="check">I’d like you to confirm compatibility</option>
                                </select>
                            </label>
                            <label v-if="id === 'email' && input.addons.email.state === 'selected' && service === 'website'" class="field wide">Which email do you need?
                                <select v-model="input.email_purpose" name="email_purpose">
                                    <option value="">Choose an answer</option><option value="inquiry">Just the included inquiry email</option><option value="other">A confirmation or another email workflow</option>
                                </select>
                            </label>
                        </div>
                    </fieldset>
                    <div class="addon priority">
                        <label for="priority"><strong>Priority delivery</strong><small>{{ catalog.rush_percent }}% of eligible one-time work, subject to availability</small></label>
                        <select id="priority" name="priority" v-model="priority"><option value="removed">Remove</option><option value="selected">Selected</option><option value="later">Save for later</option></select>
                    </div>
                    <fieldset v-if="Object.keys(months).length" class="addon-list">
                        <legend>Optional monthly work</legend>
                        <label v-for="(item, id) in months" :key="id" class="choice">
                            <input v-model="input.monthly" type="checkbox" name="monthly" :value="id"><span>{{ item.name }} · {{ money(item.amount) }}/month</span>
                        </label>
                    </fieldset>
                    <div class="field-grid">
                        <label class="field">Budget (optional)
                            <select v-model="input.budget" name="budget"><option value="">I’m not sure yet</option><option>Under ₱10,000</option><option>₱10,000–₱25,000</option><option>₱25,000–₱50,000</option><option>₱50,000+</option></select>
                        </label>
                        <label class="field">Timing (optional)
                            <select v-model="input.timing" name="timing"><option value="">I’m flexible</option><option>As soon as possible</option><option>Within a month</option><option>Within 1–3 months</option></select>
                        </label>
                    </div>
                    <div class="actions"><button type="button" class="secondary" @click="go(0)">Back</button><button :disabled="loading || !preview || !!error">Review estimate</button></div>
                </form>

                <section v-else-if="step === 2 && estimate" aria-labelledby="review-title">
                    <h2 id="review-title" ref="heading" tabindex="-1">Review your estimate</h2>
                    <p>I’ll confirm the final scope, price, and delivery date after review.</p>
                    <details v-if="outdated" class="previous">
                        <summary>Previous breakdown · {{ outdated.estimate.one_time.length ? money(outdated.estimate.total) : 'Needs review' }}</summary>
                        <EstimateLines :estimate="outdated.estimate" />
                    </details>
                    <div class="review-total"><span>{{ estimate.review.length ? 'Estimated subtotal for priced items' : 'One-time estimate' }}</span><strong>{{ cashLabel }}</strong></div>
                    <EstimateLines :estimate="estimate" />
                    <div class="actions"><button type="button" class="secondary" @click="go(1)">Back</button><button type="button" @click="outdated = null; error = ''; go(3)">{{ outdated ? 'Confirm updated estimate' : 'Request this quote' }}</button></div>
                </section>

                <form v-else-if="step === 3" class="field-stack" aria-labelledby="contact-title" @submit.prevent="submit">
                    <h2 id="contact-title" ref="heading" tabindex="-1">Where should I send your estimate?</h2>
                    <label>Your name <input v-model="contact.name" name="name" autocomplete="name" maxlength="120" required></label>
                    <label>Email address <input v-model="contact.email" name="email" type="email" autocomplete="email" maxlength="254" required></label>
                    <label>Business name (optional) <input v-model="contact.business" name="business" autocomplete="organization" maxlength="160"></label>
                    <label>Project notes (optional) <textarea v-model="contact.notes" name="notes" rows="4" maxlength="3000"></textarea></label>
                    <div class="actions"><button type="button" class="secondary" @click="go(2)">Back</button><button :disabled="sending">{{ sending ? 'Saving…' : 'Request this quote' }}</button></div>
                </form>

                <section v-else-if="confirmation" aria-labelledby="confirmation-title">
                    <h2 id="confirmation-title" ref="heading" tabindex="-1">Your request is saved</h2>
                    <p class="reference">{{ confirmation.reference }}</p>
                    <p v-if="confirmation.delivery === 'Sent'">I’ve sent a copy to your email.</p>
                    <p v-else-if="confirmation.delivery === 'Failed'">Your copy couldn’t be sent. Keep this reference for follow-up.</p>
                    <p v-else>Your email copy is pending. Keep this reference for follow-up.</p>
                    <p>I’ll review your brief and follow up about the scope, price, and timing.</p>
                    <button type="button" class="secondary" @click="restart">Start another estimate</button>
                </section>
            </article>

            <aside v-if="!confirmation" class="receipt" aria-label="Estimate">
                <p class="receipt-label">Your estimate</p>
                <template v-if="estimate">
                    <p class="receipt-name">{{ estimate.name }}</p>
                    <p class="amount" aria-live="polite">{{ cashLabel }}</p>
                    <p class="receipt-caption">{{ estimate.review.length ? 'Estimated subtotal for priced items' : 'One-time estimate' }}</p>
                    <details class="receipt-detail">
                        <summary>View estimate breakdown</summary>
                        <EstimateLines :estimate="estimate" />
                    </details>
                </template>
                <template v-else>
                    <p class="receipt-name">Start with a service</p>
                    <p class="receipt-caption">Your scope and price will appear here as you build.</p>
                    <dl class="receipt-lines preview-lines"><div><dt>Included essentials</dt><dd>—</dd></div><div><dt>One-time work</dt><dd>—</dd></div><div><dt>Saved for later</dt><dd>—</dd></div></dl>
                </template>
            </aside>
        </main>
        <footer>Estimates are in Philippine pesos</footer>
    </div>
</template>
