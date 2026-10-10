import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import dynamicallyLoadLib from '../utils/dynamicallyLoadLib';
import SupplementCheckinModal from '../modal/SupplementCheckinModal';
import type Mithril from 'mithril';
import type { ApiPayloadPlural } from 'flarum/common/Store';
import CheckinRecord from '../../common/models/CheckinRecord';
import { forumTime } from '../utils/checkin';

declare const FullCalendar: any;

export default class CheckinHistoryPage extends UserPage {
  calendar: any = null;
  removed = false;
  records: CheckinRecord[] = [];
  loadingHistory: boolean = false;

  oninit(vnode: Mithril.Vnode<any, this>): void {
    super.oninit(vnode);

    this.loadUser(m.route.param('username'));
  }

  canViewHistory(): boolean {
    if (!this.user) {
      return false;
    }
    const isSelf = !!(app.session.user && app.session.user.id() === this.user.id());
    const canViewOthers = Boolean(app.forum.attribute('canQueryOthersHistory'));

    return isSelf || canViewOthers;
  }

  content(): Mithril.Children {
    if (!this.canViewHistory()) {
      return (
        <div className="CheckinHistoryUserPage CheckinHistoryUserPage--noPermission Placeholder">
          <p>{app.translator.trans('primexcalibur-checkin-with-history.forum.page.permission-denied')}</p>
        </div>
      );
    }

    return (
      <div className="CheckinHistoryUserPage">
        <div
          id="calendar"
          oncreate={(vnode: Mithril.VnodeDOM) => {
            this.renderCalendar(vnode.dom as HTMLElement);
          }}
        />

        {this.loadingHistory && (
          <div className="DiscussionList">
            <div className="DiscussionList-loadMore">
              <LoadingIndicator size="medium" />
            </div>
          </div>
        )}
      </div>
    );
  }

  onremove(vnode: Mithril.VnodeDOM<any, this>): void {
    super.onremove(vnode);
    this.removed = true;

    if (this.calendar) {
      this.calendar.destroy();
      this.calendar = null;
    }
  }

  async getData(info: { startStr: string; endStr: string }): Promise<any[]> {
    if (!this.canViewHistory()) {
      return [];
    }

    this.loadingHistory = true;
    m.redraw();

    const username = this.user ? this.user.slug() : m.route.param('username');
    const userId = this.user ? this.user.id() : null;

    try {
      const response = await app.request<ApiPayloadPlural>({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/checkin/history`,
        params: {
          start: info.startStr.slice(0, 10),
          end: info.endStr.slice(0, 10),
          username,
          userId,
        },
      });

      this.records = app.store.pushPayload<CheckinRecord[]>(response);
      this.loadingHistory = false;
      m.redraw();

      return this.records.map((record) => ({
        id: record.id(),
        start: record.date(),
        color: record.color(),
        allDay: true,
      }));
    } catch (e) {
      this.loadingHistory = false;
      m.redraw();
      throw e;
    }
  }

  async renderCalendar(element?: HTMLElement): Promise<void> {
    if (!this.canViewHistory()) {
      return;
    }

    try {
      await dynamicallyLoadLib('fullcalendar');
      await dynamicallyLoadLib('fullcalendarLocales');
    } catch {
      app.alerts.show({ type: 'error' }, app.translator.trans('primexcalibur-checkin-with-history.forum.page.load-failed'));
      return;
    }

    const calendarEl = element || document.getElementById('calendar');
    if (!calendarEl || this.removed) {
      return;
    }

    if (this.calendar) {
      this.calendar.destroy();
      this.calendar = null;
    }

    const openModal = this.openCreateModal.bind(this);

    this.calendar = new FullCalendar.Calendar(calendarEl, {
      locale: app.translator.getLocale(),
      timeZone: 'UTC',
      now: forumTime,
      initialDate: app.forum.attribute('checkinToday'),
      allDayText: extractText(app.translator.trans('primexcalibur-checkin-with-history.forum.page.today')),
      initialView: 'dayGridMonth',
      dateClick: (info: any) => {
        openModal(info);
      },
      events: (info: any) => this.getData(info),
    });
    this.calendar.render();
  }

  async openCreateModal(info: { dateStr: string }): Promise<boolean | void> {
    // Only the user themselves can supplement check-in on their own calendar
    if (!app.session.user || !this.user || app.session.user.id() !== this.user.id()) {
      return false;
    }

    const today = forumTime().toISOString().slice(0, 10);
    if (info.dateStr > today) {
      return false;
    }

    if (this.records.some((record) => record.date() === info.dateStr)) {
      return false;
    }

    app.modal.show(SupplementCheckinModal, {
      date: info.dateStr,
      callback: () => {
        if (this.calendar) {
          this.calendar.refetchEvents();
        }
      },
    });
  }
}
