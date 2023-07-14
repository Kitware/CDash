cmake_minimum_required (VERSION 3.10)
PROJECT(SameImage)
INCLUDE(CTest)

ADD_EXECUTABLE (sameImage sameImage.cxx)
ADD_TEST (sameImage sameImage smile.gif smile.gif)
#ADD_TEST (differentImage sameImage smile.gif frown.gif)
