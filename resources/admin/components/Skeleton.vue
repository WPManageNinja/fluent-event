<template>
    <el-skeleton v-if="type === 'timeline'" v-for="row in rowCount" :key="row" animated>
        <template #template>
            <div class="fe-events-timeline__row">
                <div class="fe-events-timeline__gutter">
                    <el-skeleton-item
                        variant="text"
                        class="fe-events-timeline__skeleton-date"
                    />
                    <el-skeleton-item
                        variant="text"
                        class="fe-events-timeline__skeleton-weekday"
                    />
                </div>

                <span class="fe-events-timeline__dot" aria-hidden="true"></span>

                <div class="fe-event-card fe-event-card--skeleton">
                    <div class="fe-event-card__main">
                        <div class="fe-event-card__badges">
                            <el-skeleton-item
                                v-for="badge in 2"
                                :key="badge"
                                variant="text"
                                class="fe-events-timeline__skeleton-badge"
                            />
                        </div>

                        <el-skeleton-item
                            variant="h3"
                            class="fe-events-timeline__skeleton-title"
                        />
                        <el-skeleton-item
                            v-for="meta in 2"
                            :key="meta"
                            variant="text"
                            class="fe-events-timeline__skeleton-meta"
                        />
                    </div>

                    <el-skeleton-item variant="image" class="fe-event-card__cover" />
                </div>
            </div>
        </template>
    </el-skeleton>

    <el-skeleton v-else-if="type === 'guests'" v-for="row in rowCount" :key="`guest-${row}`" animated>
        <template #template>
            <div class="fe-guests__row">
                <div class="fe-guests__serial">
                    <el-skeleton-item variant="text" class="fe-guests__skeleton-serial" />
                </div>

                <el-skeleton-item variant="circle" class="fe-guests__skeleton-avatar" />

                <div class="fe-guests__name">
                    <el-skeleton-item variant="text" class="fe-guests__skeleton-name" />
                </div>

                <div class="fe-guests__email">
                    <el-skeleton-item variant="text" class="fe-guests__skeleton-email" />
                </div>

                <div class="fe-guests__stage">
                    <el-skeleton-item variant="text" class="fe-guests__skeleton-tag" />
                </div>

                <div class="fe-guests__actions">
                    <el-skeleton-item variant="text" class="fe-guests__skeleton-more" />
                </div>
            </div>
        </template>
    </el-skeleton>

    <el-skeleton v-else-if="type === 'team_settings'" v-for="row in rowCount" :key="`team-${row}`" animated>
        <template #template>
            <div class="fe-team__member">
                <el-skeleton-item variant="circle" class="fe-team__skeleton-avatar" />

                <div class="fe-team__identity">
                    <el-skeleton-item variant="text" class="fe-team__skeleton-name" />
                    <el-skeleton-item variant="text" class="fe-team__skeleton-email" />
                </div>

                <div class="fe-team__actions">
                    <el-skeleton-item variant="rect" class="fe-team__skeleton-action" />
                    <el-skeleton-item variant="rect" class="fe-team__skeleton-action" />
                </div>

                <div class="fe-team__permissions">
                    <el-skeleton-item variant="text" class="fe-team__skeleton-tag" />
                    <el-skeleton-item variant="text" class="fe-team__skeleton-tag" />
                    <el-skeleton-item variant="text" class="fe-team__skeleton-tag" />
                </div>
            </div>
        </template>
    </el-skeleton>

    <el-skeleton v-else-if="type === 'calendar_detail'" animated>
        <template #template>
            <el-skeleton-item variant="text" class="fe-skeleton__breadcrumb" />

            <div class="fe-skeleton__mast">
                <el-skeleton-item variant="rect" class="fe-skeleton__cover" />
                <el-skeleton-item variant="rect" class="fe-skeleton__mark" />

                <div class="fe-skeleton__identity">
                    <el-skeleton-item variant="h1" class="fe-skeleton__title" />
                    <el-skeleton-item variant="text" class="fe-skeleton__slug" />
                </div>
            </div>

            <div class="fe-skeleton__tabs">
                <el-skeleton-item
                    v-for="tab in 3"
                    :key="tab"
                    variant="rect"
                    class="fe-skeleton__tab"
                />
            </div>

            <!-- Mirrors the real two-column tab layout so nothing shifts on load. -->
            <div class="fe-calendar-tab">
                <div class="fe-calendar-tab__main">
                    <div class="fe-skeleton__tiles">
                        <el-skeleton-item
                            v-for="tile in 4"
                            :key="`stat-${tile}`"
                            variant="rect"
                            class="fe-skeleton__tile"
                        />
                    </div>

                    <div class="fe-card">
                        <el-skeleton-item
                            v-for="line in 6"
                            :key="line"
                            variant="text"
                            class="fe-skeleton__line"
                        />
                        <el-skeleton-item variant="text" class="fe-skeleton__line fe-skeleton__line--short" />
                    </div>
                </div>

                <div class="fe-calendar-tab__aside">
                    <el-skeleton-item
                        v-for="panel in 2"
                        :key="`panel-${panel}`"
                        variant="rect"
                        class="fe-skeleton__panel fe-skeleton__panel--short"
                    />
                </div>
            </div>
        </template>
    </el-skeleton>

    <el-skeleton v-else-if="type === 'event_detail'" animated>
        <template #template>
            <div class="fe-page__header">
                <div>
                    <el-skeleton-item variant="text" class="fe-skeleton__breadcrumb" />
                    <el-skeleton-item variant="h1" class="fe-skeleton__title" />
                </div>

                <el-skeleton-item variant="rect" class="fe-skeleton__button" />
            </div>

            <div class="fe-skeleton__tabs">
                <el-skeleton-item
                    v-for="tab in 4"
                    :key="tab"
                    variant="rect"
                    class="fe-skeleton__tab"
                />
            </div>

            <div class="fe-card fe-skeleton__overview">
                <el-skeleton-item variant="image" class="fe-skeleton__overview-cover" />

                <div class="fe-skeleton__overview-info">
                    <el-skeleton-item variant="h3" class="fe-skeleton__section-title" />
                    <el-skeleton-item variant="text" class="fe-skeleton__tag" />

                    <div v-for="row in 3" :key="row" class="fe-skeleton__meta-row">
                        <el-skeleton-item variant="circle" class="fe-skeleton__meta-icon" />

                        <div class="fe-skeleton__meta-lines">
                            <el-skeleton-item variant="text" class="fe-skeleton__line" />
                            <el-skeleton-item variant="text" class="fe-skeleton__line fe-skeleton__line--short" />
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </el-skeleton>

    <el-skeleton v-else-if="type === 'general_settings'" animated>
        <template #template>
            <div v-for="field in 3" :key="`input-${field}`" class="fe-skeleton__field-group">
                <el-skeleton-item variant="text" class="fe-skeleton__label" />
                <el-skeleton-item variant="rect" class="fe-skeleton__input" />
                <el-skeleton-item variant="text" class="fe-skeleton__hint" />
            </div>

            <div v-for="toggle in 2" :key="`switch-${toggle}`" class="fe-skeleton__field-group">
                <el-skeleton-item variant="text" class="fe-skeleton__label" />
                <el-skeleton-item variant="rect" class="fe-skeleton__switch" />
                <el-skeleton-item variant="text" class="fe-skeleton__hint" />
            </div>

            <div class="fe-form__actions">
                <el-skeleton-item variant="rect" class="fe-skeleton__button" />
            </div>
        </template>
    </el-skeleton>

    <el-skeleton v-else-if="type === 'event-grid'" animated>
        <template #template>
            <div class="fe-events-grid fe-events-grid--skeleton">
                <div class="fe-events-grid__skeleton-header">
                    <el-skeleton-item variant="h1" class="fe-events-grid__skeleton-month" />
                    <el-skeleton-item variant="rect" class="fe-events-grid__skeleton-nav" />
                </div>

                <div class="fe-events-grid__skeleton-cells">
                    <el-skeleton-item
                        v-for="cell in rowCount * 7"
                        :key="cell"
                        variant="rect"
                        class="fe-events-grid__skeleton-cell"
                    />
                </div>
            </div>
        </template>
    </el-skeleton>

    <el-skeleton v-else-if="type === 'event-form'" animated>
        <template #template>
            <div class="fe-event-form__grid">
                <el-skeleton-item variant="image" class="fe-event-form__skeleton-cover" />

                <div class="fe-event-form__main">
                    <el-skeleton-item variant="h1" class="fe-event-form__skeleton-title" />

                    <el-skeleton-item
                        v-for="row in rowCount"
                        :key="row"
                        variant="rect"
                        class="fe-event-form__skeleton-card"
                    />
                </div>
            </div>
        </template>
    </el-skeleton>

    <div v-else-if="type === 'calendar-grid'" class="fe-calendars__grid">
        <div v-for="card in rowCount" :key="card" class="fe-calendars__card">
            <el-skeleton animated>
                <template #template>
                    <div class="fe-calendars__card-cover">
                        <el-skeleton-item variant="rect" class="fe-skeleton__card-mark" />
                    </div>

                    <div class="fe-calendars__card-body">
                        <el-skeleton-item variant="text" class="fe-skeleton__card-title" />
                        <el-skeleton-item variant="text" class="fe-skeleton__card-slug" />

                        <div class="fe-calendars__card-meta">
                            <el-skeleton-item
                                v-for="tag in 2"
                                :key="tag"
                                variant="text"
                                class="fe-skeleton__tag"
                            />
                        </div>
                    </div>
                </template>
            </el-skeleton>
        </div>
    </div>

    <el-skeleton v-else-if="type === 'calendar-form'" animated>
        <template #template>
            <div class="fe-page__header">
                <div>
                    <el-skeleton-item variant="h1" class="fe-skeleton__title" />
                    <el-skeleton-item variant="text" class="fe-skeleton__subtitle" />
                </div>

                <el-skeleton-item variant="rect" class="fe-skeleton__button" />
            </div>

            <div class="fe-card">
                <div v-for="field in rowCount" :key="field" class="fe-skeleton__field-group">
                    <el-skeleton-item variant="text" class="fe-skeleton__label" />
                    <el-skeleton-item variant="rect" class="fe-skeleton__input" />
                </div>

                <div class="fe-skeleton__field-group">
                    <el-skeleton-item variant="text" class="fe-skeleton__label" />
                    <el-skeleton-item variant="rect" class="fe-skeleton__editor" />
                </div>

                <el-row :gutter="20">
                    <el-col v-for="column in 2" :key="column" :md="12" :sm="24">
                        <div class="fe-skeleton__field-group">
                            <el-skeleton-item variant="text" class="fe-skeleton__label" />
                            <el-skeleton-item variant="rect" class="fe-skeleton__input" />
                        </div>
                    </el-col>
                </el-row>

                <div class="fe-form__actions">
                    <el-skeleton-item
                        v-for="action in 2"
                        :key="action"
                        variant="rect"
                        class="fe-skeleton__button"
                    />
                </div>
            </div>
        </template>
    </el-skeleton>

    <el-skeleton v-else-if="type === 'dashboard'" animated>
        <template #template>
            <el-row :gutter="20">
                <el-col :md="16" :sm="24">
                    <div class="fe-skeleton__greeting">
                        <el-skeleton-item
                            variant="circle"
                            class="fe-skeleton__greeting-avatar"
                        />

                        <div>
                            <el-skeleton-item
                                variant="text"
                                class="fe-skeleton__greeting-name"
                            />
                            <el-skeleton-item
                                variant="text"
                                class="fe-skeleton__greeting-note"
                            />
                        </div>
                    </div>

                    <div class="fe-skeleton__tiles">
                        <el-skeleton-item
                            v-for="tile in 8"
                            :key="`tile-${tile}`"
                            variant="rect"
                            class="fe-skeleton__tile"
                        />
                    </div>

                    <el-skeleton-item variant="rect" class="fe-skeleton__chart" />
                </el-col>

                <el-col :md="8" :sm="24">
                    <el-skeleton-item
                        variant="rect"
                        class="fe-skeleton__panel fe-skeleton__panel--short"
                    />
                    <el-skeleton-item variant="rect" class="fe-skeleton__panel" />
                    <el-skeleton-item
                        variant="rect"
                        class="fe-skeleton__panel fe-skeleton__panel--tall"
                    />
                </el-col>
            </el-row>
        </template>
    </el-skeleton>

    <el-skeleton v-else :rows="rowCount" animated />
</template>

<script type="text/babel">
export default {
    name: 'Skeleton',
    props: {
        type: {
            required: false,
            type: String,
            default: 'plain'
        },
        rows: {
            required: false,
            type: Number,
            default: 0
        }
    },
    data() {
        return {
            defaultRows: {
                timeline: 6,
                guests: 5,
                'event-grid': 6,
                'event-form': 4,
                'calendar-grid': 6,
                'calendar-form': 2,
                plain: 4
            }
        };
    },
    computed: {
        rowCount() {
            return this.rows || this.defaultRows[this.type] || this.defaultRows.plain;
        }
    }
}
</script>
