import MonitorPage from '../../../resources/js/vue/components/MonitorPage.vue';

describe('MonitorPage', () => {
  it('explains that the monitor requires the database queue', () => {
    cy.mount(MonitorPage, {
      props: {
        usingDatabaseQueue: false,
      },
    });

    cy.contains('System Monitor only available when QUEUE_CONNECTION=database');
    cy.contains('submissions waiting to be parsed').should('not.exist');
  });

  it('shows the submission backlog when using the database queue', () => {
    cy.intercept('GET', '/api/monitor', {
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
    });

    cy.mount(MonitorPage, {
      props: {
        usingDatabaseQueue: true,
      },
    });

    cy.contains('There are currently 3 submissions waiting to be parsed.');
    cy.contains('The oldest submission was created 5 minutes ago.');
    cy.contains('/cdash/storage/logs');
    cy.contains('System Monitor only available').should('not.exist');
  });
});
