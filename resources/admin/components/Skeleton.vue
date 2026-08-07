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

    <el-row v-else-if="type === 'calendar-grid'" :gutter="20">
        <el-col v-for="card in rowCount" :key="card" :xs="24" :sm="12" :md="8">
            <div class="fe-calendars__card">
                <el-skeleton animated>
                    <template #template>
                        <div class="fe-calendars__card-head">
                            <el-skeleton-item variant="circle" class="fe-skeleton__swatch" />
                            <el-skeleton-item variant="text" class="fe-skeleton__card-title" />
                        </div>

                        <el-skeleton-item variant="text" class="fe-skeleton__card-slug" />

                        <div class="fe-calendars__card-meta">
                            <el-skeleton-item
                                v-for="tag in 2"
                                :key="tag"
                                variant="text"
                                class="fe-skeleton__tag"
                            />
                        </div>
                    </template>
                </el-skeleton>
            </div>
        </el-col>
    </el-row>

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
