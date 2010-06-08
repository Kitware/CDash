#include <iostream>
#include <string.h>
using std::cout;
using std::cerr;

int main(int argc, char** argv)
{
  if(argc < 3)
    {
    return 1;
    }

  int result = 0;
  cout << "<DartMeasurement name=\"ImageError\" type=\"numeric/double\">";
  cout << "10.0";
  cout << "</DartMeasurement>";
  cout << "<DartMeasurement name=\"BaselineImage\" type=\"text/string\">Standard</DartMeasurement>";

  if(strcmp(argv[1], argv[2]) != 0)
    {
    result = 1;
    cout <<  "<DartMeasurementFile name=\"TestImage\" type=\"image/gif\">";
    cout << argv[2];
    cout << "</DartMeasurementFile>";
    cout << "<DartMeasurementFile name=\"DifferenceImage\" type=\"image/gif\">";
    cout << argv[2];
    cout << "</DartMeasurementFile>";
    cout << "<DartMeasurementFile name=\"ValidImage\" type=\"image/gif\">";
    cout << argv[1];
    cout <<  "</DartMeasurementFile>";
    }

  cout << "<DartMeasurement name=\"WallTime\" type=\"numeric/double\">";
  cout << "1.0";
  cout << "</DartMeasurement>\n";
  cout << "<DartMeasurement name=\"CPUTime\" type=\"numeric/double\">";
  cout << "1.0";
  cout << "</DartMeasurement>\n";

  return result;
}
