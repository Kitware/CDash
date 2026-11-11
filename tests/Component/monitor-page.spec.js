import axios from 'axios';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render } from 'vitest-browser-vue';
import MonitorPage from '../../resources/js/vue/components/MonitorPage.vue';

describe('MonitorPage', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('explains that the monitor requires the database queue', async () => {
    const screen = await render(MonitorPage, {
      props: {
        usingDatabaseQueue: false,
      },
    });

    await expect.element(screen.getByText('System Monitor only available when QUEUE_CONNECTION=database')).toBeInTheDocument();
    await expect.element(screen.getByText('submissions waiting to be parsed', { exact: false })).not.toBeInTheDocument();
  });

  it('shows the submission backlog when using the database queue', async () => {
    const get = vi.spyOn(axios, 'get').mockResolvedValue({
      data: {
        backlog_length: 3,
        backlog_time: '5 minutes ago',
        time_chart_data: {
          data: [
            { color: '#00ff00', name: 'Success', values: [[1760000000, 4]] },
            { color: '#ff0000', name: 'Fail', values: [[1760000000, 1]] },
          ],
          title: 'Submissions Parsed Over the Past 1 Hours',
          xLabel: 'Date',
          yLabel: '# of Submissions',
        },
        log_directory: '/cdash/storage/logs',
      },
    });

    const screen = await render(MonitorPage, {
      props: {
        usingDatabaseQueue: true,
      },
    });

    await expect.element(screen.getByText('There are currently 3 submissions waiting to be parsed.')).toBeInTheDocument();
    await expect.element(screen.getByText('The oldest submission was created 5 minutes ago.')).toBeInTheDocument();
    await expect.element(screen.getByText('/cdash/storage/logs')).toBeInTheDocument();
    await expect.element(screen.getByText('System Monitor only available', { exact: false })).not.toBeInTheDocument();
    expect(get).toHaveBeenCalledWith('/api/monitor');
  });
});
