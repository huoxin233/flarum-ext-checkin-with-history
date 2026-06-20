import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import IndexPage from 'flarum/forum/components/IndexPage';
import Button from 'flarum/common/components/Button';
import Alert from 'flarum/common/components/Alert';
import CheckInSuccessModal from './components/checkInSuccessModal';
import CheckInFailedModal from './components/checkInFailedModal';

let isSubmittingCheckIn = false;
let autoCheckInScheduled = false;

function showCheckInFailurePrompt() {
  const forumCheckinSuccessPromptType = app.forum.attribute('forumCheckinSuccessPromptType');

  if (forumCheckinSuccessPromptType === 2) {
    app.modal.show(CheckInFailedModal);
    return;
  }

  app.alerts.show(
    Alert,
    { type: 'error' },
    `${app.translator.trans('ziven-checkin.forum.check-in-failed')} ${app.translator.trans('ziven-checkin.forum.try-again-later')}`
  );
}

function showCheckInSuccessPrompt() {
  const forumCheckinSuccessPromptText = app.forum.attribute('forumCheckinSuccessPromptText');
  const forumCheckinSuccessPromptRewardText = app.forum.attribute('forumCheckinSuccessPromptRewardText');
  const forumCheckinSuccessPromptType = app.forum.attribute('forumCheckinSuccessPromptType');
  const forumCheckinRewardMoney = app.forum.attribute('forumCheckinRewardMoney');
  if (forumCheckinSuccessPromptText === '' && forumCheckinSuccessPromptRewardText === '') {
    return;
  }

  if (forumCheckinSuccessPromptType === 2) {
    app.modal.show(CheckInSuccessModal);
    return;
  }

  if (forumCheckinSuccessPromptText !== '') {
    const checkInSuccessText = forumCheckinSuccessPromptText.replace('[days]', app.session.user.attribute('totalContinuousCheckIn'));
    app.alerts.show(Alert, { type: 'success' }, checkInSuccessText);
  }

  if (forumCheckinSuccessPromptRewardText !== '') {
    const moneyName = app.forum.attribute('huoxin-money-with-history.money_name') || app.forum.attribute('antoinefr-money.moneyname') || '[money]';
    const rewardText = moneyName.replace('[money]', forumCheckinRewardMoney);
    const checkInSuccessRewardText = forumCheckinSuccessPromptRewardText.replace('[reward]', rewardText);

    app.alerts.show(Alert, { type: 'success' }, checkInSuccessRewardText);
  }
}

function syncCurrentUser(payload) {
  app.store.pushPayload(payload);

  if (app.session.user === null || typeof app.store.getById !== 'function') {
    return;
  }

  const refreshedUser = app.store.getById('users', app.session.user.id());

  if (refreshedUser) {
    app.session.user = refreshedUser;
  }
}

function submitCheckIn() {
  if (app.session.user === null || isSubmittingCheckIn) {
    return Promise.resolve();
  }

  isSubmittingCheckIn = true;
  m.redraw();

  return app
    .request({
      method: 'POST',
      url: `${app.forum.attribute('apiUrl')}/checkin`,
    })
    .then((payload) => {
      syncCurrentUser(payload);
      showCheckInSuccessPrompt();
    })
    .catch(() => {
      showCheckInFailurePrompt();
    })
    .finally(() => {
      isSubmittingCheckIn = false;
      m.redraw();
    });
}

app.initializers.add('ziven-checkin', () => {
  extend(IndexPage.prototype, 'sidebarItems', function (items) {
    if (app.session.user !== null && app.forum.attribute('allowCheckIn') === true) {
      const totalContinuousCheckIn = app.session.user.attribute('totalContinuousCheckIn');
      const canCheckin = app.session.user.attribute('canCheckin');
      let checkinButtonText;

      if (canCheckin === true) {
        checkinButtonText = app.translator.trans('ziven-checkin.forum.check-in');
        items.add(
          'forum-checkin',
          Button.component(
            {
              icon: isSubmittingCheckIn ? 'fas fa-spinner fa-spin' : 'fas fa-calendar',
              className: 'Button CheckInButton--yellow',
              itemClassName: 'App-primaryControl',
              disabled: isSubmittingCheckIn,
              onclick: () => submitCheckIn(),
            },
            checkinButtonText
          ),
          50
        );

        const forumAutoCheckIn = app.forum.attribute('forumAutoCheckin');
        const forumAutoCheckInDelay = app.forum.attribute('forumAutoCheckInDelay');

        if (forumAutoCheckIn === 1 && autoCheckInScheduled === false && isSubmittingCheckIn === false) {
          autoCheckInScheduled = true;
          setTimeout(function () {
            if (app.session.user !== null && app.session.user.attribute('canCheckin') === true) {
              submitCheckIn();
            }
          }, forumAutoCheckInDelay);
        }
      } else {
        checkinButtonText =
          totalContinuousCheckIn <= 1
            ? app.translator.trans('ziven-checkin.forum.checked-in-day', { count: totalContinuousCheckIn })
            : app.translator.trans('ziven-checkin.forum.checked-in-days', { count: totalContinuousCheckIn });

        items.add(
          'forum-checkin',
          Button.component(
            {
              icon: 'fas fa-calendar-check',
              className: 'Button CheckInButton--green',
              itemClassName: 'App-primaryControl',
              disabled: true,
            },
            checkinButtonText
          ),
          50
        );
      }
    }
  });
});
