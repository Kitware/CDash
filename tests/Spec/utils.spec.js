import Utils from '../../resources/js/vue/components/shared/Utils';

describe('Utils', () => {
  describe('formatDuration', () => {
    it('formats a zero duration in milliseconds', () => {
      expect(Utils.formatDuration(0)).toBe('0ms');
    });

    it('formats sub-second durations in milliseconds', () => {
      expect(Utils.formatDuration(1)).toBe('1ms');
      expect(Utils.formatDuration(500)).toBe('500ms');
      expect(Utils.formatDuration(999)).toBe('999ms');
    });

    it('switches to seconds at exactly 1000ms', () => {
      expect(Utils.formatDuration(1000)).toBe('1.00s');
    });

    it('formats a mid-range second value with two decimal places', () => {
      expect(Utils.formatDuration(1500)).toBe('1.50s');
    });

    it('rounds up to 60.00s rather than switching format just under the minute threshold', () => {
      // 59999ms / 1000 = 59.999, which toFixed(2) rounds to "60.00" while still
      // taking the seconds-only branch (59999 < 60000).
      expect(Utils.formatDuration(59999)).toBe('60.00s');
    });

    it('switches to minutes and seconds at exactly 60000ms', () => {
      expect(Utils.formatDuration(60000)).toBe('1m 00s');
    });

    it('formats multi-minute durations, zero-padding seconds', () => {
      expect(Utils.formatDuration(61000)).toBe('1m 01s');
      expect(Utils.formatDuration(90000)).toBe('1m 30s');
      expect(Utils.formatDuration(125000)).toBe('2m 05s');
    });

    it('keeps accumulating minutes rather than rolling over into hours', () => {
      expect(Utils.formatDuration(3600000)).toBe('60m 00s');
      expect(Utils.formatDuration(3661000)).toBe('61m 01s');
    });
  });

  describe('formatBytes', () => {
    it('formats 0 bytes', () => {
      expect(Utils.formatBytes(0)).toBe('0 Bytes');
    });

    it('formats a value below the KiB threshold as a plain byte count', () => {
      expect(Utils.formatBytes(1)).toBe('1 Bytes');
      expect(Utils.formatBytes(1023)).toBe('1023 Bytes');
    });

    it('switches to KiB at exactly 1024 bytes', () => {
      expect(Utils.formatBytes(1024)).toBe('1.00 KiB');
    });

    it('formats a mid-range KiB value with two decimal places', () => {
      expect(Utils.formatBytes(1536)).toBe('1.50 KiB');
    });

    it('stays in KiB right up to (but not including) the MiB threshold', () => {
      // 1024^2 - 1 bytes rounds to 1024.00 when displayed with 2 decimal places,
      // since it only switches to MiB once the raw byte count reaches 1024^2.
      expect(Utils.formatBytes((1024 ** 2) - 1)).toBe('1024.00 KiB');
    });

    it('switches to MiB at exactly 1024^2 bytes', () => {
      expect(Utils.formatBytes(1024 ** 2)).toBe('1.00 MiB');
    });

    it('switches to GiB at exactly 1024^3 bytes', () => {
      expect(Utils.formatBytes(1024 ** 3)).toBe('1.00 GiB');
    });

    it('switches to TiB at exactly 1024^4 bytes', () => {
      expect(Utils.formatBytes(1024 ** 4)).toBe('1.00 TiB');
    });

    it('does not scale beyond TiB', () => {
      expect(Utils.formatBytes(1024 ** 5)).toBe('1024.00 TiB');
    });
  });

  describe('formatBytesFromKib', () => {
    it('formats 0 KiB', () => {
      expect(Utils.formatBytesFromKib(0)).toBe('0 Bytes');
    });

    it('converts a sub-KiB value down to a plain byte count', () => {
      expect(Utils.formatBytesFromKib(0.5)).toBe('512 Bytes');
    });

    it('formats exactly 1 KiB', () => {
      expect(Utils.formatBytesFromKib(1)).toBe('1.00 KiB');
    });

    it('formats a realistic MaxRSS-style KiB value', () => {
      // 14576 KiB falls in the MiB range once converted to bytes.
      expect(Utils.formatBytesFromKib(14576)).toBe(`${(14576 / 1024).toFixed(2)} MiB`);
    });

    it('scales up to MiB at exactly 1024 KiB', () => {
      expect(Utils.formatBytesFromKib(1024)).toBe('1.00 MiB');
    });

    it('scales up to GiB at exactly 1024^2 KiB', () => {
      expect(Utils.formatBytesFromKib(1024 ** 2)).toBe('1.00 GiB');
    });

    it('scales up to TiB at exactly 1024^3 KiB', () => {
      expect(Utils.formatBytesFromKib(1024 ** 3)).toBe('1.00 TiB');
    });
  });

  describe('formatBytesFromMib', () => {
    it('formats 0 MiB', () => {
      expect(Utils.formatBytesFromMib(0)).toBe('0 Bytes');
    });

    it('converts a sub-MiB value down to KiB', () => {
      expect(Utils.formatBytesFromMib(0.5)).toBe('512.00 KiB');
    });

    it('formats exactly 1 MiB', () => {
      expect(Utils.formatBytesFromMib(1)).toBe('1.00 MiB');
    });

    it('scales up to GiB at exactly 1024 MiB', () => {
      expect(Utils.formatBytesFromMib(1024)).toBe('1.00 GiB');
    });

    it('scales up to TiB at exactly 1024^2 MiB', () => {
      expect(Utils.formatBytesFromMib(1024 ** 2)).toBe('1.00 TiB');
    });
  });
});
