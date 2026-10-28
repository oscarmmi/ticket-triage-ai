<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Category = 'billing' | 'shipping' | 'product_bug' | 'account' | 'other';
type Priority = 'low' | 'medium' | 'high' | 'urgent';
type Ticket = {
    id: number;
    subject: string;
    body: string;
    channel: 'email' | 'chat' | 'phone';
    customer_name: string;
    predicted_category: Category | null;
    predicted_priority: Priority | null;
    suggested_reply: string | null;
    confidence: number | null;
    status: 'open' | 'triaged';
    is_featured: boolean;
    created_at: string;
    rationale: string | null;
    latest_model: string | null;
};

const props = defineProps<{
    tickets: Ticket[];
}>();

const tickets = ref([...props.tickets]);
const selectedId = ref(tickets.value[0]?.id ?? null);
const filter = ref<'all' | 'needs-triage' | 'triaged'>('all');
const search = ref('');
const isClassifying = ref(false);
const isSaving = ref(false);
const draft = ref(tickets.value[0]?.suggested_reply ?? '');
const errorMessage = ref('');
const toastMessage = ref('');

const selectedTicket = computed(
    () =>
        tickets.value.find((ticket) => ticket.id === selectedId.value) ?? null,
);
const liveStats = computed(() => ({
    open: tickets.value.filter((ticket) => ticket.status === 'open').length,
    triaged: tickets.value.filter((ticket) => ticket.status === 'triaged')
        .length,
    urgent: tickets.value.filter(
        (ticket) => ticket.predicted_priority === 'urgent',
    ).length,
    total: tickets.value.length,
}));

const visibleTickets = computed(() => {
    const term = search.value.trim().toLowerCase();

    return tickets.value.filter((ticket) => {
        const matchesFilter =
            filter.value === 'all' ||
            (filter.value === 'needs-triage' && ticket.status === 'open') ||
            (filter.value === 'triaged' && ticket.status === 'triaged');
        const matchesSearch =
            !term ||
            `${ticket.customer_name} ${ticket.subject} ${ticket.body}`
                .toLowerCase()
                .includes(term);

        return matchesFilter && matchesSearch;
    });
});

function selectTicket(ticket: Ticket) {
    selectedId.value = ticket.id;
    draft.value = ticket.suggested_reply ?? '';
    errorMessage.value = '';
}

function replaceTicket(updated: Ticket) {
    const index = tickets.value.findIndex((ticket) => ticket.id === updated.id);
    if (index !== -1) tickets.value[index] = updated;
    draft.value = updated.suggested_reply ?? '';
}

function csrfToken() {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

async function classify() {
    if (!selectedTicket.value || isClassifying.value) return;
    isClassifying.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(
            `/tickets/${selectedTicket.value.id}/classify`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            },
        );
        const payload = await response.json();

        if (!response.ok)
            throw new Error(
                payload.message ?? 'Could not classify this ticket.',
            );
        replaceTicket(payload.ticket as Ticket);
        if (!payload.succeeded) errorMessage.value = payload.error;
        else
            showToast(
                'AI triage complete. Review the suggested reply before sending.',
            );
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Something went wrong. Please try again.';
    } finally {
        isClassifying.value = false;
    }
}

async function acceptDraft() {
    if (!selectedTicket.value || isSaving.value) return;
    isSaving.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(
            `/tickets/${selectedTicket.value.id}/accept`,
            {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ suggested_reply: draft.value }),
            },
        );
        const payload = await response.json();

        if (!response.ok)
            throw new Error(payload.message ?? 'Could not save this reply.');
        replaceTicket(payload.ticket as Ticket);
        showToast('Draft accepted and ticket moved to triaged.');
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Something went wrong. Please try again.';
    } finally {
        isSaving.value = false;
    }
}

function showToast(message: string) {
    toastMessage.value = message;
    window.setTimeout(() => (toastMessage.value = ''), 3200);
}

function initials(name: string) {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function timeLabel(value: string) {
    const minutes = Math.max(
        1,
        Math.floor((Date.now() - new Date(value).getTime()) / 60000),
    );
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h`;
    return `${Math.floor(hours / 24)}d`;
}

function titleCase(value: string | null) {
    if (!value) return 'Unclassified';
    return value
        .replace('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
</script>

<template>
    <Head title="Support inbox">
        <meta
            name="description"
            content="Harbor Desk AI-powered support triage inbox."
        />
    </Head>

    <div class="desk-shell">
        <aside class="side-rail">
            <a href="/" class="brand-lockup" aria-label="Harbor Desk home">
                <span class="brand-mark">
                    <svg viewBox="0 0 32 32" fill="none" aria-hidden="true">
                        <path
                            d="M7 19.5c0-5 4-9 9-9s9 4 9 9v4H7v-4Z"
                            fill="currentColor"
                        />
                        <path
                            d="M11 10V7m10 3V7M5 23h22"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                        />
                        <circle cx="16" cy="17" r="2" fill="#173A40" />
                    </svg>
                </span>
                <span class="brand-type"
                    ><strong>harbor</strong><small>SUPPORT DESK</small></span
                >
            </a>

            <div class="workspace-picker">
                <span class="workspace-avatar">H</span>
                <span
                    ><strong>Harbor Desk</strong><small>Workspace</small></span
                >
                <svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path
                        d="m4 6 4 4 4-4"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                    />
                </svg>
            </div>

            <div class="rail-label">WORKSPACE</div>
            <nav class="rail-nav" aria-label="Workspace navigation">
                <button class="rail-link active" type="button">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path
                            d="M3 5.5h14M3 10h14M3 14.5h8"
                            stroke="currentColor"
                            stroke-width="1.7"
                            stroke-linecap="round"
                        />
                    </svg>
                    <span>Inbox</span
                    ><span class="rail-count">{{ liveStats.open }}</span>
                </button>
                <button
                    class="rail-link"
                    type="button"
                    @click="filter = 'needs-triage'"
                >
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path
                            d="M10 2.5 17 6v4.5c0 3.5-2.8 5.8-7 7-4.2-1.2-7-3.5-7-7V6l7-3.5Z"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linejoin="round"
                        />
                        <path
                            d="m7.5 10 1.7 1.7 3.5-3.5"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <span>Triage queue</span
                    ><span class="rail-count muted-count">{{
                        liveStats.open
                    }}</span>
                </button>
                <button
                    class="rail-link"
                    type="button"
                    @click="filter = 'triaged'"
                >
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path
                            d="M4 4h12v12H4z"
                            stroke="currentColor"
                            stroke-width="1.5"
                        />
                        <path
                            d="m7 10 2 2 4-4"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <span>Triaged</span
                    ><span class="rail-count muted-count">{{
                        liveStats.triaged
                    }}</span>
                </button>
            </nav>

            <div class="rail-label team-label">TEAM</div>
            <div class="team-entry">
                <span class="team-dot teal-dot"></span
                ><span>Customer support</span>
            </div>
            <div class="team-entry">
                <span class="team-dot violet-dot"></span
                ><span>Product specialists</span>
            </div>

            <div class="rail-bottom">
                <div class="ai-status">
                    <span class="status-pulse"></span
                    ><span
                        ><strong>AI triage active</strong
                        ><small>Rules engine · ready</small></span
                    >
                </div>
                <div class="profile-row">
                    <span class="profile-avatar">JD</span
                    ><span
                        ><strong>Jamie Dawson</strong
                        ><small>Support lead</small></span
                    ><button type="button" aria-label="Account options">
                        ···
                    </button>
                </div>
            </div>
        </aside>

        <main class="desk-main">
            <header class="topbar">
                <div class="breadcrumb">
                    <span>Workspace</span><span class="crumb-slash">/</span
                    ><strong>Inbox</strong>
                </div>
                <div class="topbar-actions">
                    <span class="today-label"
                        ><span class="live-dot"></span> All systems
                        operational</span
                    >
                    <button
                        class="icon-button"
                        type="button"
                        aria-label="Help center"
                    >
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <circle
                                cx="10"
                                cy="10"
                                r="7"
                                stroke="currentColor"
                                stroke-width="1.5"
                            />
                            <path
                                d="M8 7.7a2.1 2.1 0 1 1 3.4 1.7c-.9.7-1.4 1-1.4 2.1M10 14.1v.1"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />
                        </svg>
                    </button>
                    <button
                        class="icon-button notification-button"
                        type="button"
                        aria-label="Notifications"
                    >
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path
                                d="M15.5 8a5.5 5.5 0 0 0-11 0c0 6-2 6-2 7.5h15C17.5 14 15.5 14 15.5 8ZM8 18h4"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            /></svg
                        ><i></i>
                    </button>
                    <div class="top-avatar">JD</div>
                </div>
            </header>

            <div class="page-content">
                <section class="page-heading">
                    <div>
                        <div class="eyebrow">
                            <span class="eyebrow-mark">✳</span> CUSTOMER
                            OPERATIONS
                        </div>
                        <h1>Good morning, Jamie <span class="wave">✦</span></h1>
                        <p>
                            Here’s what’s happening across your support inbox
                            today.
                        </p>
                    </div>
                    <button
                        class="new-ticket-button"
                        type="button"
                        @click="
                            showToast(
                                'New ticket intake is ready for your helpdesk connection.',
                            )
                        "
                    >
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path
                                d="M10 4v12M4 10h12"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />
                        </svg>
                        New ticket
                    </button>
                </section>

                <section class="metrics-grid" aria-label="Inbox overview">
                    <article class="metric-card">
                        <div class="metric-top">
                            <span class="metric-label">Open tickets</span
                            ><span class="metric-icon inbox-icon"
                                ><svg
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M3 4h14v12H3zM3 11h4l1.5 2h3L13 11h4"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linejoin="round"
                                    /></svg
                            ></span>
                        </div>
                        <div class="metric-value">
                            {{ liveStats.open
                            }}<span class="metric-caption"
                                >needs attention</span
                            >
                        </div>
                        <div class="metric-foot">
                            <span class="metric-neutral-dot"></span> Across all
                            channels
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-top">
                            <span class="metric-label">Triaged today</span
                            ><span class="metric-icon triaged-icon"
                                ><svg
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M10 2.5 16.5 6v4c0 3.4-2.6 5.8-6.5 7-3.9-1.2-6.5-3.6-6.5-7V6L10 2.5Z"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                    />
                                    <path
                                        d="m7 10 2 2 4-4"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    /></svg
                            ></span>
                        </div>
                        <div class="metric-value">
                            {{ liveStats.triaged
                            }}<span class="metric-caption"
                                >ready for a reply</span
                            >
                        </div>
                        <div class="metric-foot positive-foot">
                            <span class="positive-arrow">↗</span> AI-assisted
                            workflow
                        </div>
                    </article>
                    <article class="metric-card urgent-metric">
                        <div class="metric-top">
                            <span class="metric-label">Urgent priority</span
                            ><span class="metric-icon urgent-icon"
                                ><svg
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M10 3 18 17H2L10 3Z"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        d="M10 8v4m0 2v.1"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                        stroke-linecap="round"
                                    /></svg
                            ></span>
                        </div>
                        <div class="metric-value">
                            {{ liveStats.urgent
                            }}<span class="metric-caption"
                                >needs a fast response</span
                            >
                        </div>
                        <div class="metric-foot urgent-foot">
                            <span class="urgent-indicator"></span> Escalated in
                            your inbox
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-top">
                            <span class="metric-label">Total conversations</span
                            ><span class="metric-icon total-icon"
                                ><svg
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M4 4h12v9H9l-4 3V4Z"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        d="M7 7h6M7 10h4"
                                        stroke="currentColor"
                                        stroke-width="1.4"
                                        stroke-linecap="round"
                                    /></svg
                            ></span>
                        </div>
                        <div class="metric-value">
                            {{ liveStats.total
                            }}<span class="metric-caption"
                                >in your workspace</span
                            >
                        </div>
                        <div class="metric-foot">
                            <span class="channel-mini">✉</span> Email, chat
                            &amp; phone
                        </div>
                    </article>
                </section>

                <section class="inbox-panel">
                    <div class="inbox-toolbar">
                        <div class="inbox-title-group">
                            <div>
                                <h2>Inbox</h2>
                                <p>Your team’s latest conversations</p>
                            </div>
                            <span class="inbox-total">{{
                                visibleTickets.length
                            }}</span>
                        </div>
                        <div class="toolbar-controls">
                            <label class="search-box"
                                ><svg
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="8.8"
                                        cy="8.8"
                                        r="5.8"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                    />
                                    <path
                                        d="m13.2 13.2 4 4"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                        stroke-linecap="round"
                                    /></svg
                                ><input
                                    v-model="search"
                                    type="search"
                                    placeholder="Search inbox..."
                                    aria-label="Search inbox"
                            /></label>
                            <button
                                class="filter-button"
                                type="button"
                                @click="
                                    filter =
                                        filter === 'all'
                                            ? 'needs-triage'
                                            : 'all'
                                "
                            >
                                <svg
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M3 5h14M5.5 10h9M8 15h4"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                    /></svg
                                ><span>Filter</span
                                ><span
                                    v-if="filter !== 'all'"
                                    class="filter-active-dot"
                                ></span>
                            </button>
                            <button
                                class="more-button"
                                type="button"
                                aria-label="More inbox options"
                            >
                                ···
                            </button>
                        </div>
                    </div>

                    <div class="inbox-content">
                        <div class="ticket-column">
                            <div
                                class="ticket-tabs"
                                role="tablist"
                                aria-label="Ticket status filter"
                            >
                                <button
                                    :class="{ selected: filter === 'all' }"
                                    type="button"
                                    @click="filter = 'all'"
                                >
                                    All <span>{{ liveStats.total }}</span>
                                </button>
                                <button
                                    :class="{
                                        selected: filter === 'needs-triage',
                                    }"
                                    type="button"
                                    @click="filter = 'needs-triage'"
                                >
                                    Open <span>{{ liveStats.open }}</span>
                                </button>
                                <button
                                    :class="{ selected: filter === 'triaged' }"
                                    type="button"
                                    @click="filter = 'triaged'"
                                >
                                    Triaged
                                </button>
                            </div>

                            <div class="ticket-list">
                                <button
                                    v-for="ticket in visibleTickets"
                                    :key="ticket.id"
                                    :class="[
                                        'ticket-card',
                                        { active: ticket.id === selectedId },
                                    ]"
                                    type="button"
                                    @click="selectTicket(ticket)"
                                >
                                    <div class="ticket-card-top">
                                        <span
                                            :class="[
                                                'channel-icon',
                                                `${ticket.channel}-channel`,
                                            ]"
                                        >
                                            <svg
                                                v-if="
                                                    ticket.channel === 'email'
                                                "
                                                viewBox="0 0 20 20"
                                                fill="none"
                                                aria-hidden="true"
                                            >
                                                <rect
                                                    x="3"
                                                    y="4.5"
                                                    width="14"
                                                    height="11"
                                                    rx="2"
                                                    stroke="currentColor"
                                                    stroke-width="1.5"
                                                />
                                                <path
                                                    d="m4 6 6 4.5L16 6"
                                                    stroke="currentColor"
                                                    stroke-width="1.5"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>
                                            <svg
                                                v-else-if="
                                                    ticket.channel === 'chat'
                                                "
                                                viewBox="0 0 20 20"
                                                fill="none"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    d="M3 4.5h14v10H9l-4 2v-2H3v-10Z"
                                                    stroke="currentColor"
                                                    stroke-width="1.5"
                                                    stroke-linejoin="round"
                                                />
                                                <path
                                                    d="M6 8h8M6 11h5"
                                                    stroke="currentColor"
                                                    stroke-width="1.3"
                                                    stroke-linecap="round"
                                                />
                                            </svg>
                                            <svg
                                                v-else
                                                viewBox="0 0 20 20"
                                                fill="none"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    d="m6.2 3.5 2.2 3.2-1.5 1.7a12 12 0 0 0 4.7 4.7l1.7-1.5 3.2 2.2-.7 2.7c-.2.7-.9 1.1-1.6 1A14.9 14.9 0 0 1 2.8 5.8c-.1-.7.3-1.4 1-1.6l2.4-.7Z"
                                                    stroke="currentColor"
                                                    stroke-width="1.4"
                                                    stroke-linejoin="round"
                                                />
                                            </svg> </span
                                        ><span
                                            v-if="ticket.is_featured"
                                            class="featured-label"
                                            >SAMPLE</span
                                        ><span class="ticket-time">{{
                                            timeLabel(ticket.created_at)
                                        }}</span>
                                    </div>
                                    <div class="ticket-customer-row">
                                        <span
                                            class="customer-avatar"
                                            :class="`avatar-${ticket.id % 5}`"
                                            >{{
                                                initials(ticket.customer_name)
                                            }}</span
                                        ><strong>{{
                                            ticket.customer_name
                                        }}</strong
                                        ><span
                                            v-if="
                                                ticket.predicted_priority ===
                                                'urgent'
                                            "
                                            class="urgent-spark"
                                            >✳</span
                                        >
                                    </div>
                                    <div class="ticket-subject">
                                        {{ ticket.subject }}
                                    </div>
                                    <div class="ticket-excerpt">
                                        {{ ticket.body }}
                                    </div>
                                    <div class="ticket-card-footer">
                                        <span
                                            :class="[
                                                'category-chip',
                                                ticket.predicted_category
                                                    ? `category-${ticket.predicted_category}`
                                                    : 'category-pending',
                                            ]"
                                            >{{
                                                titleCase(
                                                    ticket.predicted_category,
                                                )
                                            }}</span
                                        ><span
                                            v-if="ticket.predicted_priority"
                                            :class="[
                                                'priority-chip',
                                                `priority-${ticket.predicted_priority}`,
                                            ]"
                                            ><i></i
                                            >{{
                                                titleCase(
                                                    ticket.predicted_priority,
                                                )
                                            }}</span
                                        ><span v-else class="needs-triage-chip"
                                            ><i></i>Needs triage</span
                                        >
                                    </div>
                                </button>
                                <div
                                    v-if="visibleTickets.length === 0"
                                    class="empty-list"
                                >
                                    <span>⌕</span
                                    ><strong>No tickets found</strong
                                    ><small
                                        >Try a different search or
                                        filter.</small
                                    >
                                </div>
                            </div>
                            <div class="list-footer">
                                <span class="list-footer-dot"></span>
                                {{ visibleTickets.length }} conversations
                                <span class="footer-separator">·</span> Updated
                                just now
                            </div>
                        </div>

                        <div v-if="selectedTicket" class="ticket-detail">
                            <div class="detail-toolbar">
                                <div class="detail-toolbar-left">
                                    <button
                                        class="detail-icon-button"
                                        type="button"
                                        aria-label="Back to list"
                                    >
                                        <svg
                                            viewBox="0 0 20 20"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="m12.5 4.5-5.5 5.5 5.5 5.5"
                                                stroke="currentColor"
                                                stroke-width="1.7"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg></button
                                    ><span class="detail-divider"></span
                                    ><span class="ticket-reference"
                                        >Ticket #HD-{{
                                            String(selectedTicket.id).padStart(
                                                4,
                                                '0',
                                            )
                                        }}</span
                                    >
                                </div>
                                <div class="detail-toolbar-right">
                                    <button
                                        class="detail-icon-button"
                                        type="button"
                                        aria-label="Bookmark ticket"
                                    >
                                        <svg
                                            viewBox="0 0 20 20"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M5 3.5h10v13l-5-3-5 3v-13Z"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                                stroke-linejoin="round"
                                            />
                                        </svg></button
                                    ><button
                                        class="detail-icon-button"
                                        type="button"
                                        aria-label="More ticket options"
                                    >
                                        ···
                                    </button>
                                </div>
                            </div>

                            <div class="detail-scroll">
                                <div class="ticket-heading">
                                    <div class="ticket-heading-overline">
                                        <span
                                            :class="[
                                                'status-badge',
                                                selectedTicket.status === 'open'
                                                    ? 'status-open'
                                                    : 'status-triaged',
                                            ]"
                                            ><i></i
                                            >{{
                                                selectedTicket.status === 'open'
                                                    ? 'Open'
                                                    : 'Triaged'
                                            }}</span
                                        ><span class="detail-channel"
                                            >via
                                            {{ selectedTicket.channel }}</span
                                        ><span class="heading-time"
                                            >{{
                                                timeLabel(
                                                    selectedTicket.created_at,
                                                )
                                            }}
                                            ago</span
                                        >
                                    </div>
                                    <h2>{{ selectedTicket.subject }}</h2>
                                    <div class="detail-customer">
                                        <span
                                            class="customer-avatar large-avatar"
                                            :class="`avatar-${selectedTicket.id % 5}`"
                                            >{{
                                                initials(
                                                    selectedTicket.customer_name,
                                                )
                                            }}</span
                                        ><span
                                            ><strong>{{
                                                selectedTicket.customer_name
                                            }}</strong
                                            ><small
                                                >Customer · Harbor Desk</small
                                            ></span
                                        ><button
                                            class="customer-more"
                                            type="button"
                                            aria-label="Customer details"
                                        >
                                            ↗
                                        </button>
                                    </div>
                                </div>

                                <div class="message-card">
                                    <div class="message-header">
                                        <span class="message-label"
                                            ><span class="message-symbol"
                                                >✉</span
                                            >
                                            CUSTOMER MESSAGE</span
                                        ><button
                                            type="button"
                                            class="message-more"
                                            aria-label="Message actions"
                                        >
                                            ···
                                        </button>
                                    </div>
                                    <p>{{ selectedTicket.body }}</p>
                                    <div class="message-meta">
                                        <span
                                            >Received
                                            {{
                                                new Date(
                                                    selectedTicket.created_at,
                                                ).toLocaleDateString('en-US', {
                                                    month: 'short',
                                                    day: 'numeric',
                                                })
                                            }}</span
                                        ><span class="meta-dot">·</span
                                        ><span
                                            >{{
                                                selectedTicket.channel
                                            }}
                                            channel</span
                                        >
                                    </div>
                                </div>

                                <div
                                    v-if="selectedTicket.predicted_category"
                                    class="ai-insight-card"
                                >
                                    <div class="insight-heading">
                                        <span class="insight-icon">✳</span>
                                        <div>
                                            <strong>AI triage insight</strong
                                            ><small
                                                >Classification ready for
                                                review</small
                                            >
                                        </div>
                                        <span class="confidence-pill"
                                            >{{
                                                Math.round(
                                                    (selectedTicket.confidence ??
                                                        0) * 100,
                                                )
                                            }}% confidence</span
                                        >
                                    </div>
                                    <div class="insight-tags">
                                        <span
                                            :class="[
                                                'category-chip',
                                                `category-${selectedTicket.predicted_category}`,
                                            ]"
                                            >{{
                                                titleCase(
                                                    selectedTicket.predicted_category,
                                                )
                                            }}</span
                                        ><span
                                            :class="[
                                                'priority-chip',
                                                `priority-${selectedTicket.predicted_priority}`,
                                            ]"
                                            ><i></i
                                            >{{
                                                titleCase(
                                                    selectedTicket.predicted_priority,
                                                )
                                            }}
                                            priority</span
                                        >
                                    </div>
                                    <p
                                        v-if="selectedTicket.rationale"
                                        class="insight-rationale"
                                    >
                                        <strong>Why:</strong>
                                        {{ selectedTicket.rationale }}
                                    </p>
                                    <div v-else class="insight-rationale">
                                        <strong>Why:</strong> Suggested from the
                                        message context and support intent.
                                    </div>
                                </div>

                                <div v-else class="triage-empty-state">
                                    <span class="empty-sparkle">✳</span>
                                    <div>
                                        <strong>Ready for AI triage</strong>
                                        <p>
                                            Classify this ticket to suggest a
                                            category, priority, and
                                            customer-ready reply.
                                        </p>
                                    </div>
                                </div>

                                <div class="reply-section">
                                    <div class="reply-heading">
                                        <div>
                                            <span class="reply-icon">↗</span>
                                            <h3>Suggested reply</h3>
                                        </div>
                                        <span
                                            v-if="
                                                selectedTicket.suggested_reply
                                            "
                                            class="ai-generated-label"
                                            ><span>✳</span> AI GENERATED</span
                                        >
                                    </div>
                                    <textarea
                                        v-model="draft"
                                        :placeholder="
                                            selectedTicket.predicted_category
                                                ? 'Edit the suggested reply before accepting...'
                                                : 'Run AI triage to generate a suggested reply...'
                                        "
                                        :disabled="
                                            !selectedTicket.suggested_reply
                                        "
                                        aria-label="Suggested reply"
                                    ></textarea>
                                    <div class="reply-footer">
                                        <span
                                            ><svg
                                                viewBox="0 0 16 16"
                                                fill="none"
                                                aria-hidden="true"
                                            >
                                                <circle
                                                    cx="8"
                                                    cy="8"
                                                    r="6"
                                                    stroke="currentColor"
                                                    stroke-width="1.2"
                                                />
                                                <path
                                                    d="M8 7v4m0-6v.1"
                                                    stroke="currentColor"
                                                    stroke-width="1.2"
                                                    stroke-linecap="round"
                                                />
                                            </svg>
                                            Review before sending to
                                            customer</span
                                        ><span
                                            >{{ draft.length }} characters</span
                                        >
                                    </div>
                                </div>

                                <div
                                    v-if="errorMessage"
                                    class="inline-error"
                                    role="alert"
                                >
                                    <span>!</span>{{ errorMessage }}
                                </div>
                            </div>

                            <div class="detail-actions">
                                <button
                                    class="classify-button"
                                    type="button"
                                    :disabled="isClassifying"
                                    @click="classify"
                                >
                                    <svg
                                        v-if="!isClassifying"
                                        viewBox="0 0 20 20"
                                        fill="none"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="m10 2 1.5 5.5L17 9l-5.5 1.5L10 16l-1.5-5.5L3 9l5.5-1.5L10 2Z"
                                            stroke="currentColor"
                                            stroke-width="1.4"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            d="m16 13 .7 2.3L19 16l-2.3.7L16 19l-.7-2.3L13 16l2.3-.7L16 13Z"
                                            fill="currentColor"
                                        /></svg
                                    ><span v-else class="button-spinner"></span
                                    >{{
                                        isClassifying
                                            ? 'Classifying...'
                                            : selectedTicket.predicted_category
                                              ? 'Re-run AI triage'
                                              : 'Classify with AI'
                                    }}
                                </button>
                                <button
                                    class="accept-button"
                                    type="button"
                                    :disabled="
                                        !selectedTicket.suggested_reply ||
                                        isSaving
                                    "
                                    @click="acceptDraft"
                                >
                                    <svg
                                        v-if="!isSaving"
                                        viewBox="0 0 20 20"
                                        fill="none"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="m4 10 4 4 8-8"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        /></svg
                                    ><span
                                        v-else
                                        class="button-spinner dark-spinner"
                                    ></span
                                    >{{
                                        isSaving
                                            ? 'Saving...'
                                            : selectedTicket.status ===
                                                'triaged'
                                              ? 'Save reply'
                                              : 'Accept draft'
                                    }}
                                </button>
                            </div>
                        </div>
                        <div v-else class="ticket-detail detail-empty">
                            <span>✳</span><strong>Select a conversation</strong>
                            <p>
                                Choose a ticket from your inbox to see the
                                conversation and triage details.
                            </p>
                        </div>
                    </div>
                </section>
                <footer class="page-footer">
                    <span>© 2026 Harbor Desk</span
                    ><span
                        ><span class="footer-green-dot"></span>AI assists. Your
                        team decides.</span
                    ><a
                        href="https://github.com/oscarmmi/ticket-triage-ai"
                        target="_blank"
                        rel="noreferrer"
                        >Built for thoughtful support <span>↗</span></a
                    >
                </footer>
            </div>
        </main>

        <Transition name="toast"
            ><div v-if="toastMessage" class="toast-message" role="status">
                <span>✓</span>{{ toastMessage }}
            </div></Transition
        >
    </div>
</template>
