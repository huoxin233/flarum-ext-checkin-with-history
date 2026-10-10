import app from 'flarum/forum/app';

export default function checkinMessages(): string[] {
  const days = String(app.session.user?.attribute('totalContinuousCheckIn') || 0);
  const amount = String(app.forum.attribute('forumCheckinRewardMoney') || 0);
  const moneyName = String(app.forum.attribute('huoxin-money-with-history.money_name') || '[money]');
  const message = String(app.forum.attribute('forumCheckinSuccessPromptText') || '').replace('[days]', days);
  const reward = String(app.forum.attribute('forumCheckinSuccessPromptRewardText') || '').replace('[reward]', moneyName.replace('[money]', amount));

  return [message, reward].filter(Boolean);
}
