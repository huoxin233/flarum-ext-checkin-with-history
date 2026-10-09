import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserPage from 'flarum/forum/components/UserPage';
import UserCard from 'flarum/forum/components/UserCard';
import LinkButton from 'flarum/common/components/LinkButton';
import CheckinHistoryPage from './pages/CheckinHistoryPage';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

app.initializers.add('mattoid-checkin-history', () => {
  app.routes['user.checkin.history'] = {
    path: '/u/:username/checkin/history',
    component: CheckinHistoryPage,
  };

  extend(UserCard.prototype, 'infoItems', function (items: ItemList<Mithril.Children>) {
    const user = this.attrs.user;

    if (!user) {
      return;
    }

    const checkinCard = user.attribute('checkinCard') as number | undefined;
    if (checkinCard !== undefined && checkinCard !== null) {
      items.add(
        'checkinCard',
        <span>{app.translator.trans('mattoid-daily-check-in-history.forum.page.checkin-card-count', { count: checkinCard })}</span>
      );
    }
  });

  extend(UserPage.prototype, 'navItems', function (items: ItemList<Mithril.Children>) {
    if (!this.user) {
      return;
    }

    const isSelf = !!(app.session.user && app.session.user.id() === this.user.id());
    const canViewOthers = Boolean(app.forum.attribute('canQueryOthersHistory'));

    if (!isSelf && !canViewOthers) {
      return;
    }

    items.add(
      'post-checkin-history',
      LinkButton.component(
        {
          href: app.route('user.checkin.history', {
            username: this.user.slug(),
          }),
          icon: 'fas fa-calendar-alt',
        },
        app.translator.trans('mattoid-daily-check-in-history.forum.page.link-name')
      )
    );
  });
});
