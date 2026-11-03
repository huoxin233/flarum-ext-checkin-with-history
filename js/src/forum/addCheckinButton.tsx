import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import IndexPage from 'flarum/forum/components/IndexPage';
import Button from 'flarum/common/components/Button';
import extractText from 'flarum/common/utils/extractText';
import CheckinResultModal from './components/CheckinResultModal';
import { submitCheckIn } from './utils/checkin';
import checkinMessages from './utils/checkinMessages';

let submitting = false;
let autoCheckInScheduled = false;

async function checkIn(): Promise<void> {
  if (!app.session.user || submitting) return;

  submitting = true;
  m.redraw();
  const promptType = Number(app.forum.attribute('forumCheckinSuccessPromptType'));

  try {
    await submitCheckIn();
    if (promptType === 2 && checkinMessages().length) {
      app.modal.show(CheckinResultModal, { success: true });
    } else if (promptType === 1) {
      checkinMessages().forEach((message) => app.alerts.show({ type: 'success' }, message));
    }
  } catch {
    if (promptType === 2) {
      app.modal.show(CheckinResultModal, { success: false });
    } else {
      app.alerts.show(
        { type: 'error' },
        `${extractText(app.translator.trans('primexcalibur-checkin-with-history.forum.check-in-failed'))} ${extractText(
          app.translator.trans('primexcalibur-checkin-with-history.forum.try-again-later')
        )}`
      );
    }
  } finally {
    submitting = false;
    m.redraw();
  }
}

export default function addCheckinButton(): void {
  extend(IndexPage.prototype, 'sidebarItems', function (items) {
    const user = app.session.user;
    if (!user || !app.forum.attribute('allowCheckIn')) return;

    const canCheckin = Boolean(user.attribute('canCheckin'));
    const days = Number(user.attribute('totalContinuousCheckIn') || 0);
    const label = canCheckin
      ? app.translator.trans('primexcalibur-checkin-with-history.forum.check-in')
      : app.translator.trans('primexcalibur-checkin-with-history.forum.checked-in-days', { count: days });
    const shifted = app.current.get('routeName') === 'tag' && 'fof-follow-tags' in flarum.extensions;

    items.add(
      'forum-checkin',
      <Button
        icon={submitting ? 'fas fa-spinner fa-spin' : canCheckin ? 'fas fa-calendar' : 'fas fa-calendar-check'}
        className={`Button CheckInButton--${canCheckin ? 'yellow' : 'green'}${shifted ? ' CheckInButton--shifted' : ''}`}
        aria-label={extractText(label)}
        itemClassName="App-primaryControl"
        disabled={!canCheckin || submitting}
        onclick={checkIn}
      >
        {label}
      </Button>,
      50
    );

    if (canCheckin && Number(app.forum.attribute('forumAutoCheckin')) === 1 && !autoCheckInScheduled) {
      autoCheckInScheduled = true;
      setTimeout(() => {
        if (app.session.user?.attribute('canCheckin')) checkIn();
      }, Number(app.forum.attribute('forumAutoCheckinDelay') || 0));
    }
  });
}
